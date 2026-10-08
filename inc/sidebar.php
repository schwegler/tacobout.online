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
	$html = '<section class="tacobout-discovery-section"><h3>' . esc_html( $trending ? __( 'Trending Posts', 'tacobout' ) : __( 'Latest Posts', 'tacobout' ) ) . '</h3>';
	$html .= '<p class="tacobout-sidebar-caption">' . esc_html( $trending ? __( 'Most viewed over the past 7 days', 'tacobout' ) : __( 'Recent posts while view stats are unavailable', 'tacobout' ) ) . '</p><ul class="tacobout-sidebar-links">';
	foreach ( $posts as $post ) {
		$html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
	}
	return $html . '</ul></section>';
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
		$reviews[] = array( 'url' => $url, 'title' => preg_replace( '/\s+/u', ' ', trim( $text->textContent ) ), 'quote' => trim( $quote->textContent ) );
		if ( count( $reviews ) === 3 ) {
			break;
		}
	}
	return $reviews;
}

function tacobout_public_reviews() {
	$cached = get_transient( 'tacobout_public_reviews' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$response = wp_safe_remote_get( 'https://trove.schweg.xyz/', array( 'timeout' => 3, 'redirection' => 0, 'limit_response_size' => 524288, 'headers' => array( 'Accept' => 'text/html' ) ) );
	$reviews = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ? tacobout_parse_public_reviews( wp_remote_retrieve_body( $response ) ) : array();
	set_transient( 'tacobout_public_reviews', $reviews, empty( $reviews ) ? 300 : 900 );
	return $reviews;
}

function tacobout_discovery_sidebar() {
	$html = '<aside class="tacobout-discovery-sidebar" aria-label="' . esc_attr__( 'Explore more', 'tacobout' ) . '"><section class="tacobout-discovery-section"><h3>' . esc_html__( 'HTML Playground', 'tacobout' ) . '</h3><p><a href="https://infopages.pages.dev/">' . esc_html__( 'Explore my InfoPages →', 'tacobout' ) . '</a></p></section>';
	$html .= '<section class="tacobout-discovery-section"><h3>' . esc_html__( 'Latest public reviews', 'tacobout' ) . '</h3><ul class="tacobout-sidebar-links">';
	foreach ( tacobout_public_reviews() as $review ) {
		$html .= '<li><a href="' . esc_url( $review['url'] ) . '">' . esc_html( $review['title'] ) . '</a><p>' . esc_html( wp_trim_words( $review['quote'], 28 ) ) . '</p></li>';
	}
	$html .= '</ul><p><a href="https://trove.schweg.xyz/">' . esc_html__( 'More on Trove →', 'tacobout' ) . '</a></p></section>';
	return $html . tacobout_trending_shortcode() . '</aside>';
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
