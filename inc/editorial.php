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

/** One intentional author portrait, without assigning it to guest contributors. */
function tacobout_author_mascot_shortcode() {
	$author = get_queried_object();
	if ( ! is_author() || ! $author || 'schwegler' !== ( $author->user_nicename ?? '' ) ) {
		return '';
	}
	return '<figure class="tacobout-author-mascot"><img src="' . esc_url( get_theme_file_uri( 'assets/brand/schweg-mascot-420.png' ) ) . '" srcset="' . esc_url( get_theme_file_uri( 'assets/brand/schweg-mascot-420.png' ) ) . ' 420w, ' . esc_url( get_theme_file_uri( 'assets/brand/schweg-mascot.png' ) ) . ' 1125w" sizes="130px" width="1125" height="1365" alt="' . esc_attr__( 'Schweg’s illustrated mascot holding a purple pencil', 'tacobout' ) . '" decoding="async" /></figure>';
}
add_action( 'init', function () { add_shortcode( 'tacobout_author_mascot', 'tacobout_author_mascot_shortcode' ); } );


function tacobout_creator_credit_shortcode() {
	return '<div class="tacobout-creator-footer"><a class="creator-credit" href="https://schweg.xyz/" aria-label="A schweg.xyz project"><span>a</span><img class="creator-logo-light" src="' . esc_url( get_theme_file_uri( 'assets/brand/schweg-xyz-ink.svg' ) ) . '" alt="schweg.xyz" width="132" height="42" loading="lazy" decoding="async"><img class="creator-logo-dark" src="' . esc_url( get_theme_file_uri( 'assets/brand/schweg-xyz-light.svg' ) ) . '" alt="schweg.xyz" width="132" height="42" loading="lazy" decoding="async"><span>project</span></a><div class="creator-footer-links"><a class="creator-contact" href="mailto:andrew@schweg.xyz">Contact Andrew</a><a class="creator-support" href="https://paypal.me/schwegler">Help with hosting<span class="screen-reader-text"> via PayPal</span></a></div></div>';
}
add_action( 'init', function () { add_shortcode( 'tacobout_creator_credit', 'tacobout_creator_credit_shortcode' ); } );
