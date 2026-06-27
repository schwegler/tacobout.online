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
 * Add preconnect resource hints for Google Fonts.
 * This optimization reduces DNS lookup, TCP handshake, and TLS negotiation time for the font files,
 * resulting in faster text rendering and reducing layout shifts.
 */
function tacobout_resource_hints( $urls, $relation_type ) {
	if ( wp_style_is( 'tacobout-google-fonts', 'queue' ) && 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.googleapis.com',
			'crossorigin',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'tacobout_resource_hints', 10, 2 );

/**
 * Load Google Fonts asynchronously.
 * This optimization prevents the external font stylesheet from blocking the initial page render,
 * significantly improving First Contentful Paint (FCP) and overall page load speed.
 */
function tacobout_async_google_fonts( $tag, $handle, $href, $media ) {
	if ( 'tacobout-google-fonts' === $handle ) {
		$tag = sprintf(
			'<link rel="stylesheet" id="%s-css" href="%s" media="print" onload="this.media=\'all\'" />' . "\n" .
			'<noscript><link rel="stylesheet" href="%s" media="%s" /></noscript>' . "\n",
			esc_attr( $handle ),
			esc_url( $href ),
			esc_url( $href ),
			esc_attr( $media )
		);
	}
	return $tag;
}
add_filter( 'style_loader_tag', 'tacobout_async_google_fonts', 10, 4 );

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

/**
 * CRITICAL: Clear saved template customizations from the database.
 * When templates are edited in the Site Editor, WordPress saves them to the
 * database as wp_template / wp_template_part custom post types. These saved
 * versions OVERRIDE the theme's file-based templates permanently.
 *
 * This runs once on version 2.0.0 to ensure the new templates take effect.
 * After running, it sets a flag so it won't run again.
 */
function tacobout_clear_saved_templates() {
	$version_flag = 'tacobout_templates_cleared_v2';
	if ( get_option( $version_flag ) ) {
		return; // Already cleared for this version
	}

	// Delete all saved template customizations for this theme
	$template_types = array( 'wp_template', 'wp_template_part' );
	foreach ( $template_types as $post_type ) {
		$posts = get_posts( array(
			'post_type'   => $post_type,
			'post_status' => 'any',
			'numberposts' => -1,
			'tax_query'   => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => get_stylesheet(),
				),
			),
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	update_option( $version_flag, true );
}
add_action( 'after_setup_theme', 'tacobout_clear_saved_templates' );

/**
 * Add body class when query block pagination is active.
 * WordPress doesn't add .paged for query block pagination (?query-1-page=2),
 * only for traditional pagination (?paged=2). This fixes that so our CSS
 * hero selector body.home:not(.paged) works correctly.
 */
function tacobout_pagination_body_class( $classes ) {
	// Check for query block pagination params
	foreach ( $_GET as $key => $value ) {
		if ( preg_match( '/^query(?:-\d+)?-page$/', $key ) && intval( $value ) > 1 ) {
			$classes[] = 'paged';
			break;
		}
	}
	return $classes;
}
add_filter( 'body_class', 'tacobout_pagination_body_class' );

/**
 * Add security headers to responses
 * This improves defense in depth by preventing MIME-type sniffing,
 * clickjacking, and cross-site scripting (XSS) attacks.
 */
function tacobout_security_headers() {
	if ( ! is_admin() ) {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-XSS-Protection: 1; mode=block' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}
add_action( 'send_headers', 'tacobout_security_headers' );

/**
 * Disable XML-RPC to mitigate brute-force and DDoS attacks.
 * XML-RPC is a legacy feature often abused by attackers.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Remove WordPress version generator meta tag to reduce information leakage.
 */
remove_action( 'wp_head', 'wp_generator' );
