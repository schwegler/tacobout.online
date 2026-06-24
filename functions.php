<?php
/**
 * Tacobout Social 2.0 — functions and definitions
 * A personal magazine theme with deep Bluesky/ATProto integration.
 */

if ( ! function_exists( 'tacobout_support' ) ) :
	function tacobout_support() {
		add_editor_style( 'style.css' );
		load_theme_textdomain( 'tacobout' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'post-thumbnails' );

		// Tumblog post formats
		add_theme_support( 'post-formats', array(
			'status',
			'image',
			'video',
			'quote',
			'link',
			'audio',
			'gallery',
		) );
	}
endif;
add_action( 'after_setup_theme', 'tacobout_support' );

/**
 * Enqueue styles
 */
function tacobout_enqueue_styles() {
	wp_enqueue_style(
		'tacobout-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);

	// Google Fonts — Space Grotesk (headings) + Instrument Sans (body)
	wp_enqueue_style(
		'tacobout-google-fonts',
		'https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_styles' );

/**
 * CRITICAL: Add post format classes to the post wrapper in Query Loops.
 * WordPress FSE does NOT add format-{type} classes to posts in query loops.
 * This filter fixes that, which is why the CSS was being "ignored" before.
 */
function tacobout_post_class( $classes, $extra_classes, $post_id ) {
	$format = get_post_format( $post_id );
	if ( $format ) {
		$classes[] = 'tacobout-format-' . $format;
	} else {
		$classes[] = 'tacobout-format-standard';
	}
	return $classes;
}
add_filter( 'post_class', 'tacobout_post_class', 10, 3 );

/**
 * Register custom block styles
 */
function tacobout_register_block_styles() {
	register_block_style( 'core/post-template', array(
		'name'  => 'tacobout-magazine',
		'label' => 'Magazine Grid',
	) );
}
add_action( 'init', 'tacobout_register_block_styles' );

/**
 * Add oEmbed max width for better responsive embeds
 */
function tacobout_embed_defaults( $defaults ) {
	$defaults['width']  = 900;
	$defaults['height'] = 506;
	return $defaults;
}
add_filter( 'embed_defaults', 'tacobout_embed_defaults' );
