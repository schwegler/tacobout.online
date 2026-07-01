<?php
/**
 * Tacobout Social 2.0 — functions and definitions
 * A personal magazine theme with deep Bluesky/ATProto integration.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
	$stylesheet     = get_stylesheet();
	foreach ( $template_types as $post_type ) {
		$posts = get_posts( array(
			'post_type'   => $post_type,
			'post_status' => 'any',
			'numberposts' => -1,
			'tax_query'   => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => $stylesheet,
				),
			),
			'fields'      => 'ids',
		) );
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
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
		if ( str_starts_with( $key, 'query' ) && str_ends_with( $key, 'page' ) ) {
			if ( $key === 'query-page' || preg_match( '/^query-\d+-page$/', $key ) ) {
				if ( intval( $value ) > 1 ) {
					$classes[] = 'paged';
					break;
				}
			}
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

/**
 * ⚡ Bolt: Short-circuit block rendering for blocks hidden by CSS
 * In query loops, certain post formats have their content, excerpt, or featured image
 * hidden via CSS (display: none). Rendering these blocks only to hide them wastes
 * backend CPU time and increases the HTML payload size.
 * This filter intercepts the rendering and returns an empty string for those blocks.
 */
function tacobout_pre_render_hidden_blocks( $pre_render, $parsed_block, $parent_block ) {
	$block_name = $parsed_block['blockName'] ?? '';

	// Only target specific blocks that might be hidden
	$target_blocks = array( 'core/post-content', 'core/post-featured-image', 'core/post-excerpt' );
	if ( ! in_array( $block_name, $target_blocks, true ) ) {
		return $pre_render;
	}

	$post_id = $parent_block->context['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return $pre_render;
	}

	$is_in_query_loop = $parent_block && ! empty( $parent_block->context['queryId'] );
	$format           = get_post_format( $post_id ) ?: 'standard';
	$should_hide      = false;

	if ( 'core/post-content' === $block_name && 'standard' === $format && $is_in_query_loop ) {
		$should_hide = true;
	} elseif ( 'core/post-excerpt' === $block_name && $is_in_query_loop ) {
		$hidden_formats = array( 'video', 'audio', 'status', 'aside', 'image', 'quote', 'link' );
		if ( in_array( $format, $hidden_formats, true ) ) {
			$should_hide = true;
		}
	} elseif ( 'core/post-featured-image' === $block_name ) {
		// Hide featured image for non-standard formats BOTH in query loops AND single posts
		$hidden_formats = array( 'video', 'audio', 'status', 'aside', 'image', 'quote', 'link' );
		if ( in_array( $format, $hidden_formats, true ) ) {
			$should_hide = true;
		}
	}

	if ( $should_hide ) {
		return ''; // Short-circuit render
	}

	return $pre_render;
}
add_filter( 'pre_render_block', 'tacobout_pre_render_hidden_blocks', 10, 3 );

/**
 * Inject an interaction badge into each post card in query loops.
 * Shows total comments (WP + ActivityPub + Atmosphere — all stored as WP comments).
 * Badge is hidden when count is 0.
 */
function tacobout_interaction_badge( $block_content, $block ) {
	// Use regex to find each <li...class="...wp-block-post..."> and inject the badge
	$block_content = preg_replace_callback(
		'/<li\s[^>]*class="[^"]*wp-block-post[^"]*"[^>]*>/i',
		function ( $matches ) {
		// Extract post ID from the post-{id} class (WordPress's get_post_class format)
			if ( preg_match( '/[ "]post-([0-9]+)[ "]/', $matches[0], $id_match ) ) {
				$post_id = intval( $id_match[1] );
			} else {
				return $matches[0];
			}

			$count = intval( get_comments_number( $post_id ) );
			if ( $count < 1 ) {
				return $matches[0];
			}

			$label = sprintf(
				/* translators: %d: interaction count */
				_n( '%d interaction', '%d interactions', $count, 'tacobout' ),
				$count
			);

			$badge = sprintf(
				'<a href="%s" class="tacobout-interaction-badge" aria-label="%s" title="%s">💬 %d</a>',
				esc_url( get_permalink( $post_id ) ),
				esc_attr( $label ),
				esc_attr( $label ),
				$count
			);

			return $matches[0] . $badge;
		},
		$block_content
	);

	return $block_content;
}
add_filter( 'render_block_core/post-template', 'tacobout_interaction_badge', 10, 2 );

/**
 * Register custom REST API fields for infinite scroll.
 * Exposes post_format and interaction_count on the posts endpoint.
 */
function tacobout_register_rest_fields() {
	register_rest_field( 'post', 'post_format', array(
		'get_callback' => function ( $post ) {
			$format = get_post_format( $post['id'] );
			return $format ? $format : 'standard';
		},
		'schema' => array(
			'description' => 'Post format (standard, video, audio, etc.)',
			'type'        => 'string',
			'context'     => array( 'view' ),
		),
	) );

	register_rest_field( 'post', 'interaction_count', array(
		'get_callback' => function ( $post ) {
			return intval( get_comments_number( $post['id'] ) );
		},
		'schema' => array(
			'description' => 'Total interaction count (comments + fediverse + bluesky)',
			'type'        => 'integer',
			'context'     => array( 'view' ),
		),
	) );
}
add_action( 'rest_api_init', 'tacobout_register_rest_fields' );

/**
 * Enqueue infinite scroll + scroll-to-top script on the home page.
 */
function tacobout_enqueue_infinite_scroll() {


	wp_enqueue_script(
		'tacobout-infinite-scroll',
		get_template_directory_uri() . '/tacobout-infinite-scroll.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true // Load in footer
	);

	// Calculate total pages
	$per_page    = 9;
	$total_posts = wp_count_posts()->publish;
	$total_pages = ceil( $total_posts / $per_page );

	wp_localize_script( 'tacobout-infinite-scroll', 'tacoboutScroll', array(
		'restUrl'    => esc_url_raw( rest_url( 'wp/v2/posts' ) ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'perPage'    => $per_page,
		'totalPages' => $total_pages,
		'siteUrl'    => esc_url( home_url() ),
	) );
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_infinite_scroll' );


/**
 * Enqueue script for ALT text badges on images
 */
function tacobout_enqueue_alt_badge() {
	wp_enqueue_script(
		'tacobout-alt-badge',
		get_template_directory_uri() . '/tacobout-alt-badge.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true // Load in footer
	);
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_alt_badge' );
