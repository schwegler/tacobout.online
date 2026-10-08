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
	$html = '<section class="tacobout-discovery-section"><h3>' . esc_html( $trending ? __( 'Trending this week', 'tacobout' ) : __( 'Latest Posts', 'tacobout' ) ) . '</h3>';
	$html .= '<p class="tacobout-sidebar-caption">' . esc_html( $trending ? __( 'Most viewed over the past 7 days', 'tacobout' ) : __( 'Recent posts while view stats are unavailable', 'tacobout' ) ) . '</p><ol class="tacobout-sidebar-links tacobout-trending-list">';
	foreach ( $posts as $index => $post ) {
		$html .= '<li><a class="tacobout-trending-link" href="' . esc_url( get_permalink( $post ) ) . '"><span class="tacobout-trending-rank">' . esc_html( sprintf( '%02d', $index + 1 ) ) . '</span><span class="tacobout-trending-copy"><strong>' . esc_html( get_the_title( $post ) ) . '</strong><span class="tacobout-sidebar-meta">' . esc_html( get_the_date( '', $post ) ) . '</span></span>' . get_the_post_thumbnail( $post, 'thumbnail', array( 'class' => 'tacobout-trending-image', 'alt' => '', 'loading' => 'lazy' ) ) . '</a></li>';
	}
	return $html . '</ol></section>';
}

/** Parse only review cards from Trove's anonymous public activity feed. */
function tacobout_parse_public_reviews( $html ) {
	if ( ! class_exists( 'DOMDocument' ) || '' === trim( $html ) ) {
		return array();
	}
	$previous = libxml_use_internal_errors( true );
	$document = new DOMDocument();
	$loaded = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( ! $loaded ) {
		return array();
	}
	$xpath = new DOMXPath( $document );
	$reviews = array();
	foreach ( $xpath->query( '//*[@id="activity_feed"]//*[contains(concat(" ", normalize-space(@class), " "), " activity-card ")]' ) as $card ) {
		$quote = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " activity-review-quote ")]', $card )->item( 0 );
		$link = $xpath->query( './/a[contains(concat(" ", normalize-space(@class), " "), " activity-item-link ")]', $card )->item( 0 );
		$text = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " activity-text ")]', $card )->item( 0 );
		if ( ! $quote || ! $link || ! $text ) {
			continue;
		}
		$href = $link->getAttribute( 'href' );
		$url = tacobout_trove_item_url( str_starts_with( $href, '/' ) ? 'https://trove.schweg.xyz' . $href : $href );
		if ( '' === $url ) {
			continue;
		}
		$review = array( 'url' => $url, 'title' => preg_replace( '/\s+/u', ' ', trim( $text->textContent ) ), 'quote' => trim( $quote->textContent ) );
		$review['item_title'] = trim( $link->textContent );
		$author = $xpath->query( './/a[contains(@class, "activity-user-link")]', $card )->item( 0 );
		$time = $xpath->query( './/*[contains(@class, "activity-time")]', $card )->item( 0 );
		$image = $xpath->query( './/*[contains(@class, "activity-thumbnail")]//img', $card )->item( 0 );
		$review['author'] = $author ? trim( $author->textContent ) : '';
		$review['time'] = $time ? trim( $time->textContent ) : '';
		$review['image'] = $image ? tacobout_sidebar_image_url( $image->getAttribute( 'src' ) ) : '';
		$review['rating'] = preg_match( '/Rating:\s*([0-5](?:\.[0-9]+)?)\s*★/u', $review['title'], $rating ) ? $rating[1] : '';
		$reviews[] = $review;
		if ( count( $reviews ) === 3 ) {
			break;
		}
	}
	return $reviews;
}

function tacobout_public_reviews() {
	$cached = get_transient( 'tacobout_public_reviews_v2' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$response = wp_safe_remote_get( 'https://trove.schweg.xyz/', array( 'timeout' => 3, 'redirection' => 0, 'limit_response_size' => 524288, 'headers' => array( 'Accept' => 'text/html' ) ) );
	$reviews = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ? tacobout_parse_public_reviews( wp_remote_retrieve_body( $response ) ) : array();
	set_transient( 'tacobout_public_reviews_v2', $reviews, empty( $reviews ) ? 300 : 900 );
	return $reviews;
}

/** Public HTTPS covers only; never allow credentials or executable URLs. */
function tacobout_sidebar_image_url( $url ) {
	$parts = wp_parse_url( $url );
	return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? $url : '';
}

function tacobout_sidebar_arrow() {
	return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>';
}

function tacobout_discovery_sidebar() {
	$html = '<aside class="tacobout-discovery-sidebar" aria-label="' . esc_attr__( 'Explore more', 'tacobout' ) . '">';
	$html .= '<section class="tacobout-discovery-section tacobout-review-section"><div class="tacobout-sidebar-heading"><h3>' . esc_html__( 'Latest on Trove', 'tacobout' ) . '</h3><a href="https://trove.schweg.xyz/">' . esc_html__( 'View all', 'tacobout' ) . ' ' . tacobout_sidebar_arrow() . '</a></div><p class="tacobout-sidebar-caption">' . esc_html__( 'From the public review feed', 'tacobout' ) . '</p><ul class="tacobout-sidebar-links tacobout-review-list">';
	$reviews = tacobout_public_reviews();
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
		if ( ! empty( $review['rating'] ) ) {
			$html .= '<span class="tacobout-review-rating" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'tacobout' ), $review['rating'] ) ) . '"><span aria-hidden="true">★</span> ' . esc_html( $review['rating'] ) . '<span class="tacobout-sidebar-meta"> / 5</span></span>';
		}
		$html .= '</span></a><blockquote>' . esc_html( wp_trim_words( $review['quote'], 24 ) ) . '</blockquote></li>';
	}
	if ( empty( $reviews ) ) {
		$html .= '<li class="tacobout-sidebar-empty">' . esc_html__( 'Discover what people are reading, watching, and listening to on Trove.', 'tacobout' ) . '</li>';
	}
	return $html . '</ul></section>' . tacobout_trending_shortcode() . '<a class="tacobout-playground" href="https://infopages.pages.dev/"><span><strong>' . esc_html__( 'HTML Playground', 'tacobout' ) . '</strong><span class="tacobout-sidebar-meta">' . esc_html__( 'A few experiments on InfoPages', 'tacobout' ) . '</span></span>' . tacobout_sidebar_arrow() . '</a></aside>';
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
