<?php
/** Shared editorial navigation and query safeguards. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Utilities have useful non-JavaScript navigation and no remote dependency. */
function tacobout_utilities_shortcode() {
	return '<div class="tacobout-utilities"><a class="tacobout-search-link" href="' . esc_url( home_url( '/?s=' ) ) . '" aria-label="' . esc_attr__( 'Search the blog', 'tacobout' ) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg><span class="screen-reader-text">' . esc_html__( 'Search', 'tacobout' ) . '</span></a><button type="button" class="tacobout-theme-control" hidden>' . esc_html__( 'Change color theme', 'tacobout' ) . '</button></div>';
}


function tacobout_search_summary_shortcode() {
	global $wp_query;
	$count = (int) $wp_query->found_posts;
	return '<p class="tacobout-search-summary">' . esc_html( sprintf( _n( '%s result', '%s results', $count, 'tacobout' ), number_format_i18n( $count ) ) ) . '</p>';
}
function tacobout_register_editorial_shortcodes() {
	add_shortcode( 'tacobout_utilities', 'tacobout_utilities_shortcode' );
	add_shortcode( 'tacobout_search_summary', 'tacobout_search_summary_shortcode' );
}
add_action( 'init', 'tacobout_register_editorial_shortcodes' );

/** Saved theme query overrides must not silently replace an archive/search context. */
function tacobout_inherit_archive_query( $block ) {
	if ( 'core/query' !== ( $block['blockName'] ?? '' ) || ! ( is_archive() || is_search() ) ) {
		return $block;
	}
	$theme_query = 'tacobout/feed' === ( $block['attrs']['namespace'] ?? '' );
	foreach ( $block['innerBlocks'] ?? array() as $child ) {
		$theme_query = $theme_query || ( 'core/pattern' === $child['blockName'] && 'tacobout/query' === ( $child['attrs']['slug'] ?? '' ) );
		$theme_query = $theme_query || ( 'core/post-template' === $child['blockName'] && false !== strpos( $child['attrs']['className'] ?? '', 'tacobout-magazine-grid' ) );
	}
	if ( $theme_query ) {
		$block['attrs']['query']['inherit'] = true;
	}
	return $block;
}
add_filter( 'render_block_data', 'tacobout_inherit_archive_query' );

function tacobout_enqueue_ui() {
	if ( tacobout_is_editor_preview() ) {
		return;
	}
	wp_enqueue_style( 'tacobout-editorial', get_theme_file_uri( 'editorial.css' ), array( 'tacobout-style' ), wp_get_theme()->get( 'Version' ) );
	// Head execution applies a stored preference before body paint on every template.
	wp_enqueue_script( 'tacobout-ui', get_theme_file_uri( 'tacobout-ui.js' ), array(), wp_get_theme()->get( 'Version' ), false );
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_ui' );
