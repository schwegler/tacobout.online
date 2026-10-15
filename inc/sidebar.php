<?php
/** Shared discovery sidebar and recent, view-based post ranking. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cache rankings, never expose analytics credentials or private post titles. */
function tacobout_trending_posts() {
	$rows = get_transient( 'tacobout_trending_week' );
	if ( false === $rows ) {
		$rows = function_exists( 'stats_get_csv' ) ? stats_get_csv( 'postviews', array( 'days' => 7, 'limit' => 100 ) ) : array();
		$rows = is_array( $rows ) ? $rows : array();
		$rows = array_filter( $rows, function ( $row ) {
			return is_array( $row ) && ! empty( $row['post_id'] ) && isset( $row['views'] ) && is_numeric( $row['views'] ) && (int) $row['views'] > 0;
		} );
		usort( $rows, function ( $a, $b ) {
			return (int) $b['views'] <=> (int) $a['views'];
		} );
		set_transient( 'tacobout_trending_week', $rows, empty( $rows ) ? 300 : 1800 );
	}
	$posts = array();
	foreach ( $rows as $row ) {
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password || ( is_single() && get_queried_object_id() === $post->ID ) ) {
			continue;
		}
		$posts[ $post->ID ] = $post;
		if ( count( $posts ) === 3 ) {
			break;
		}
	}
	return array_values( $posts );
}

function tacobout_trending_shortcode() {
	$posts = tacobout_trending_posts();
	$trending = ! empty( $posts );
	if ( ! $trending ) {
		$posts = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish', 'has_password' => false, 'exclude' => is_single() ? array( get_queried_object_id() ) : array() ) );
	}
	$bolt_icon = '<svg class="tacobout-sidebar-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
	$mascot_hype = esc_url( get_theme_file_uri( 'assets/brand/telly_pose_04_breaking_hype.png' ) );
	$title = $trending ? __( 'Hot on the Wire', 'tacobout' ) : __( 'Latest Posts', 'tacobout' );
	$caption = $trending ? __( 'Most read over the past 7 days', 'tacobout' ) : __( 'Recent posts while view stats are unavailable', 'tacobout' );

	$html = '<section class="tacobout-discovery-section"><div class="tacobout-sidebar-heading"><h3>' . $bolt_icon . ' ' . esc_html( $title ) . '</h3><img src="' . $mascot_hype . '" alt="" class="mascot-sticker tacobout-sidebar-mascot" /></div>';
	$html .= '<p class="tacobout-sidebar-caption">' . esc_html( $caption ) . '</p><ol class="tacobout-sidebar-links tacobout-trending-list">';
	foreach ( $posts as $index => $post ) {
		$html .= '<li><a class="tacobout-trending-link" href="' . esc_url( get_permalink( $post ) ) . '"><span class="tacobout-trending-rank">' . esc_html( sprintf( '%02d', $index + 1 ) ) . '</span><span class="tacobout-trending-copy"><strong>' . esc_html( get_the_title( $post ) ) . '</strong><span class="tacobout-sidebar-meta">' . esc_html( get_the_date( '', $post ) ) . '</span></span>' . get_the_post_thumbnail( $post, 'thumbnail', array( 'class' => 'tacobout-trending-image', 'alt' => '', 'loading' => 'lazy' ) ) . '</a></li>';
	}
	return $html . '</ol></section>';
}

/** Validate the public JSON contract before it can reach a stored snapshot. */
function tacobout_parse_review_response( $body ) {
	$envelope = json_decode( $body );
	if ( ! is_object( $envelope ) || ! isset( $envelope->reviews ) || ! is_array( $envelope->reviews ) ) {
		return null;
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || ! isset( $data['reviews'] ) || ! is_array( $data['reviews'] ) || ! array_is_list( $data['reviews'] ) ) {
		return null;
	}
	$reviews = array();
	foreach ( array_slice( $data['reviews'], 0, 3 ) as $review ) {
		if ( ! is_array( $review ) || ! is_string( $review['id'] ?? null ) || ! is_string( $review['body'] ?? null ) || '' === trim( $review['body'] ) || ! is_string( $review['item_title'] ?? null ) || ! is_string( $review['item_url'] ?? null ) || ! is_string( $review['reviewer']['display_name'] ?? null ) ) {
			return null;
		}
		$url = tacobout_trove_item_url( $review['item_url'] );
		if ( '' === $url ) {
			return null;
		}
		$rating = $review['rating'] ?? null;
		if ( null !== $rating && ( ! is_numeric( $rating ) || $rating < 0 || $rating > 5 ) ) {
			return null;
		}
		$reviews[] = array(
			'id' => $review['id'],
			'url' => $url,
			'item_title' => $review['item_title'],
			'quote' => $review['body'],
			'author' => $review['reviewer']['display_name'],
			'image' => is_string( $review['cover_url'] ?? null ) ? tacobout_sidebar_image_url( $review['cover_url'] ) : '',
			'rating' => null === $rating ? '' : (string) $rating,
		);
	}
	return $reviews;
}

/** Stored bodies may be reused only after Trove validates their ETag. */
function tacobout_public_reviews() {
	$snapshot = get_option( 'tacobout_reviews_api_snapshot', array() );
	$headers = array( 'Accept' => 'application/json' );
	if ( is_array( $snapshot ) && is_string( $snapshot['etag'] ?? null ) && '' !== $snapshot['etag'] && is_array( $snapshot['reviews'] ?? null ) ) {
		$headers['If-None-Match'] = $snapshot['etag'];
	}
	$response = wp_safe_remote_get( 'https://trove.schweg.xyz/api/reviews?limit=3', array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 131072, 'headers' => $headers ) );
	if ( is_wp_error( $response ) ) {
		return array();
	}
	$status = wp_remote_retrieve_response_code( $response );
	if ( 304 === $status && isset( $headers['If-None-Match'] ) ) {
		return $snapshot['reviews'];
	}
	if ( 200 !== $status ) {
		return array();
	}
	$reviews = tacobout_parse_review_response( wp_remote_retrieve_body( $response ) );
	if ( null === $reviews ) {
		return array();
	}
	$etag = wp_remote_retrieve_header( $response, 'etag' );
	update_option( 'tacobout_reviews_api_snapshot', array( 'reviews' => $reviews, 'etag' => is_string( $etag ) ? $etag : '' ), false );
	return $reviews;
}

/** Retire polling and HTML snapshots; they cannot satisfy review revalidation. */
function tacobout_retire_review_polling() {
	if ( get_option( 'tacobout_reviews_api_migrated' ) ) {
		return;
	}
	wp_clear_scheduled_hook( 'tacobout_refresh_public_reviews' );
	wp_clear_scheduled_hook( 'tacobout_refresh_review_page' );
	delete_transient( 'tacobout_reviews_refresh_lock' );
	delete_transient( 'tacobout_public_reviews_v2' );
	delete_option( 'tacobout_reviews_snapshot' );
	update_option( 'tacobout_reviews_api_migrated', true, false );
}
add_action( 'init', 'tacobout_retire_review_polling' );

/** Cacheable HTML contains only a placeholder, never a saved review body. */
add_action( 'wp_enqueue_scripts', function () {
	if ( tacobout_is_editor_preview() ) {
		return;
	}
	wp_enqueue_script( 'tacobout-reviews', get_theme_file_uri( 'tacobout-reviews.js' ), array(), wp_get_theme()->get( 'Version' ), true );
} );

function tacobout_review_rest_response() {
	return new WP_REST_Response( array( 'html' => tacobout_review_section( tacobout_public_reviews() ) ), 200, array( 'Cache-Control' => 'no-store, private', 'Vary' => 'Cookie' ) );
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'tacobout/v1', '/reviews', array( 'methods' => 'GET', 'callback' => 'tacobout_review_rest_response', 'permission_callback' => '__return_true' ) );
} );

/** Public HTTPS covers only; never allow credentials or executable URLs. */
function tacobout_sidebar_image_url( $url ) {
	$parts = wp_parse_url( $url );
	return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? $url : '';
}

function tacobout_sidebar_arrow() {
	return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>';
}

function tacobout_review_section( $reviews ) {
	if ( empty( $reviews ) ) {
		return '';
	}
	$gem_icon = '<svg class="tacobout-sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M6 3h12l4 7-10 11L2 10l4-7z"/><path d="M2 10h20"/><path d="M12 21L7.5 10 10 3"/><path d="M12 21l4.5-11L14 3"/></svg>';
	$mascot_facet = esc_url( get_theme_file_uri( 'assets/brand/facet_pose_04_five_star.png' ) );
	$html = '<section class="tacobout-discovery-section tacobout-review-section"><div class="tacobout-sidebar-heading"><h3>' . $gem_icon . ' ' . esc_html__( 'Freshly Logged in Trove', 'tacobout' ) . '</h3><div class="tacobout-sidebar-heading-right"><img src="' . $mascot_facet . '" alt="" class="mascot-sticker tacobout-sidebar-mascot" /><a href="https://trove.schweg.xyz/">' . esc_html__( 'Explore the shelf', 'tacobout' ) . ' ' . tacobout_sidebar_arrow() . '</a></div></div><p class="tacobout-sidebar-caption">' . esc_html__( 'Live from the public review vault', 'tacobout' ) . '</p><ul class="tacobout-sidebar-links tacobout-review-list">';
	foreach ( $reviews as $review ) {
		$html .= '<li><a class="tacobout-review-link" href="' . esc_url( $review['url'] ) . '">';
		$image = tacobout_sidebar_image_url( $review['image'] ?? '' );
		if ( $image ) {
			$html .= '<img class="tacobout-review-cover" src="' . esc_url( $image ) . '" width="64" height="88" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" />';
		}
		$html .= '<span class="tacobout-review-copy"><strong>' . esc_html( $review['item_title'] ?? $review['title'] ) . '</strong>';
		if ( ! empty( $review['author'] ) ) {
			$html .= '<span class="tacobout-sidebar-meta">' . esc_html( $review['author'] ) . '</span>';
		}
		if ( isset( $review['rating'] ) && '' !== $review['rating'] ) {
			$html .= '<span class="tacobout-review-rating" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'tacobout' ), $review['rating'] ) ) . '"><span aria-hidden="true">★</span> ' . esc_html( $review['rating'] ) . '<span class="tacobout-sidebar-meta"> / 5</span></span>';
		}
		$html .= '</span></a><blockquote>' . esc_html( wp_trim_words( $review['quote'], 24 ) ) . '</blockquote></li>';
	}
	return $html . '</ul></section>';
}

function tacobout_discovery_sidebar() {
	$html = '<aside class="tacobout-discovery-sidebar" aria-label="' . esc_attr__( 'Explore more', 'tacobout' ) . '">';
	$html .= '<div data-tacobout-reviews="' . esc_url( rest_url( 'tacobout/v1/reviews' ) ) . '" hidden></div>';
	return $html . tacobout_trending_shortcode() . '<a class="tacobout-playground" href="https://infopages.pages.dev/"><span><strong>' . esc_html__( 'HTML Playground', 'tacobout' ) . '</strong><span class="tacobout-sidebar-meta">' . esc_html__( 'A few experiments on InfoPages', 'tacobout' ) . '</span></span>' . tacobout_sidebar_arrow() . '</a></aside>';
}

/** Keep the sidebar inside the list so masonry can fill around and below it. */
function tacobout_grid_sidebar( $content, $block ) {
	if ( ! is_home() || is_paged() || false === strpos( $block['attrs']['className'] ?? '', 'tacobout-magazine-grid' ) || false === strpos( $content, '</ul>' ) ) {
		return $content;
	}
	$content = preg_replace( '/(<ul\b[^>]*class=")[^"]*\K(?=")/', ' tacobout-grid-with-sidebar', $content, 1 );
	$position = strrpos( $content, '</ul>' );
	return substr_replace( $content, '<li class="tacobout-grid-sidebar">' . tacobout_discovery_sidebar() . '</li>', $position, 0 );
}
add_filter( 'render_block_core/post-template', 'tacobout_grid_sidebar', 20, 2 );
add_action( 'init', function () {
	add_shortcode( 'tacobout_trending', 'tacobout_trending_shortcode' );
} );

/**
 * Refresh theme sidebar columns even when a Site Editor template is saved in
 * the database. Preserve the column wrapper and replace only its contents.
 */
function tacobout_single_discovery_sidebar( $content, $block ) {
	$classes = preg_split( '/\s+/', trim( $block['attrs']['className'] ?? '' ) );
	if ( ! is_single() || ! in_array( 'tacobout-sidebar-sticky', $classes, true ) ) {
		return $content;
	}
	if ( ! preg_match( '/^\s*(<div\b[^>]*>)/i', $content, $wrapper ) ) {
		return $content;
	}
	return $wrapper[1] . tacobout_discovery_sidebar() . '</div>';
}
add_filter( 'render_block_core/column', 'tacobout_single_discovery_sidebar', 20, 2 );
add_action( 'init', function () {
	add_shortcode( 'tacobout_discovery', 'tacobout_discovery_sidebar' );
} );
