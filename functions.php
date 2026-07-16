<?php
/**
 * Tacobout Social 2.0 — functions and definitions
 * A personal magazine theme with deep Bluesky/ATProto integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! function_exists( 'tacobout_support' ) ) :
	function tacobout_support() {
		add_editor_style( 'style.css' );
		load_theme_textdomain( 'tacobout' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'post-thumbnails' );

		// Tumblog post formats
		add_theme_support(
			'post-formats',
			array(
				'status',
				'image',
				'video',
				'quote',
				'link',
				'audio',
				'gallery',
			)
		);
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
			'href' => 'https://fonts.googleapis.com',
			'crossorigin',
		);
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
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
	register_block_style(
		'core/post-template',
		array(
			'name'  => 'tacobout-magazine',
			'label' => 'Magazine Grid',
		)
	);
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
		$posts = get_posts(
			array(
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
			)
		);
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
 * Helper function to get interaction count with caching to prevent N+1 queries.
 */
function tacobout_get_interaction_count( $post_id ) {
	$cache_key = 'tacobout_interactions_' . $post_id;
	$count     = wp_cache_get( $cache_key, 'tacobout' );

	if ( false === $count ) {
		$count = (int) get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'count'   => true,
				'type'    => 'all',
			)
		);
		wp_cache_set( $cache_key, $count, 'tacobout' );
	}

	return $count;
}

/**
 * Invalidate interaction count cache when post cache is cleaned.
 */
function tacobout_invalidate_interaction_count_cache( $post_id ) {
	wp_cache_delete( 'tacobout_interactions_' . $post_id, 'tacobout' );
}
add_action( 'clean_post_cache', 'tacobout_invalidate_interaction_count_cache' );

/** * Inject an interaction badge into each post card in query loops.
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

			$count = tacobout_get_interaction_count( $post_id );
			if ( $count < 1 ) {
				return $matches[0];
			}

			$label = sprintf(
				/* translators: %d: interaction count */
				_n( '%d interaction', '%d interactions', $count, 'tacobout' ),
				$count
			);

			$badge = sprintf(
				'<a href="%s" class="tacobout-interaction-badge" aria-label="%s" title="%s"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> %d</a>',
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
	register_rest_field(
		'post',
		'post_format',
		array(
			'get_callback' => function ( $post ) {
				$format = get_post_format( $post['id'] );
				return $format ? $format : 'standard';
			},
			'schema'       => array(
				'description' => 'Post format (standard, video, audio, etc.)',
				'type'        => 'string',
				'context'     => array( 'view' ),
			),
		)
	);

	register_rest_field(
		'post',
		'interaction_count',
		array(
			'get_callback' => function ( $post ) {
				return tacobout_get_interaction_count( $post['id'] );
			},
			'schema'       => array(
				'description' => 'Total interaction count (comments + fediverse + bluesky)',
				'type'        => 'integer',
				'context'     => array( 'view' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'tacobout_register_rest_fields' );

/**
 * Enqueue infinite scroll + scroll-to-top script on the home page.
 * Guarded against loading inside the Site Editor's preview iframe,
 * which would run heavy observers/fetch logic on top of the editor's
 * React app and crash Safari.
 */
function tacobout_enqueue_infinite_scroll() {
	if ( isset( $_GET['wp_theme_preview'] ) || is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'tacobout-infinite-scroll',
		get_template_directory_uri() . '/tacobout-infinite-scroll.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true // Load in footer
	);

	// Calculate total pages (global, unfiltered)
	$per_page    = 9;
	$total_posts = wp_count_posts()->publish;
	$total_pages = ceil( $total_posts / $per_page );

	// Detect taxonomy archive context for the overflow separator feature.
	// On category/tag pages, the initial WP query shows filtered posts; infinite
	// scroll will mirror that filter until exhausted, then show the global feed.
	$term_id          = null;
	$term_name        = null;
	$term_type        = null; // 'categories' or 'tags' — WP REST API filter param name
	$term_rest_field  = null; // REST API field name to filter by
	$term_total_pages = null;

	if ( is_category() ) {
		$queried          = get_queried_object();
		$term_id          = $queried->term_id;
		$term_name        = $queried->name;
		$term_type        = 'categories';
		$term_rest_field  = 'categories';
		$term_count       = $queried->count;
		$term_total_pages = ceil( $term_count / $per_page );
	} elseif ( is_tag() ) {
		$queried          = get_queried_object();
		$term_id          = $queried->term_id;
		$term_name        = $queried->name;
		$term_type        = 'tags';
		$term_rest_field  = 'tags';
		$term_count       = $queried->count;
		$term_total_pages = ceil( $term_count / $per_page );
	}

	wp_localize_script(
		'tacobout-infinite-scroll',
		'tacoboutScroll',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'wp/v2/posts' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'perPage'        => $per_page,
			'totalPages'     => $total_pages,
			'siteUrl'        => esc_url( home_url() ),
			'termId'         => $term_id,
			'termName'       => $term_name ? esc_html( $term_name ) : null,
			'termType'       => $term_rest_field,
			'termTotalPages' => $term_total_pages,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_infinite_scroll' );


/**
 * Enqueue script for ALT text badges on images.
 * Guarded against loading inside the Site Editor's preview iframe.
 */
function tacobout_enqueue_alt_badge() {
	if ( isset( $_GET['wp_theme_preview'] ) || is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'tacobout-alt-badge',
		get_template_directory_uri() . '/tacobout-alt-badge.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true // Load in footer
	);
}
add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_alt_badge' );

/**
 * Prevent login redirection plugins from breaking the Enable Mastodon Apps OAuth flow.
 */
function tacobout_enable_mastodon_apps_login_redirect( $redirect_to, $requested_redirect_to ) {
	if ( isset( $_REQUEST['action'] ) && 'enable-mastodon-apps-authenticate' === $_REQUEST['action'] ) {
		$sanitized_redirect = wp_sanitize_redirect( $requested_redirect_to );
		$scheme             = strtolower( (string) wp_parse_url( $sanitized_redirect, PHP_URL_SCHEME ) );

		if ( empty( $scheme ) || in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return wp_validate_redirect( $sanitized_redirect, $redirect_to );
		}

		if ( in_array( $scheme, array( 'javascript', 'vbscript', 'data' ), true ) ) {
			return $redirect_to; // Fallback to default if malicious scheme detected.
		}

		return $sanitized_redirect;
	}
	return $redirect_to;
}
add_filter( 'login_redirect', 'tacobout_enable_mastodon_apps_login_redirect', 999, 2 );

/**
 * Register stub REST endpoints and rewrite rules for the Mastodon Apps v2 API.
 * This fixes crashes in Mastodon clients like Ivory or Mastodon for iOS when they hit
 * these endpoints and receive a full HTML 404 response instead of JSON.
 */
function tacobout_enable_mastodon_apps_v2_stubs() {
	// Add rewrite rules to map the /api/v2/... URLs directly to the REST API routes.
	add_rewrite_rule( '^api/v2/filters/?$', 'index.php?rest_route=/enable-mastodon-apps/api/v2/filters', 'top' );
	add_rewrite_rule( '^api/v2/notifications/policy/?$', 'index.php?rest_route=/enable-mastodon-apps/api/v2/notifications/policy', 'top' );
}
add_action( 'init', 'tacobout_enable_mastodon_apps_v2_stubs' );

function tacobout_enable_mastodon_apps_v2_rest_routes() {
	register_rest_route(
		'enable-mastodon-apps',
		'/api/v2/filters',
		array(
			'methods'             => 'GET',
			'callback'            => '__return_empty_array',
			'permission_callback' => '__return_true', // Allows app to fetch an empty filter list without crashing
		)
	);

	register_rest_route(
		'enable-mastodon-apps',
		'/api/v2/notifications/policy',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return array(
					'for_not_following'    => 'accept',
					'for_not_followers'    => 'accept',
					'for_new_accounts'     => 'accept',
					'for_private_mentions' => 'accept',
					'for_limited_accounts' => 'accept',
					'summary'              => array(
						'pending_requests_count'      => 0,
						'pending_notifications_count' => 0,
					),
				);
			},
			'permission_callback' => '__return_true', // Allows app to fetch policy without crashing
		)
	);
}
add_action( 'rest_api_init', 'tacobout_enable_mastodon_apps_v2_rest_routes' );

/**
 * Flush rewrite rules to ensure the Mastodon app v2 endpoints work.
 * This runs once per theme version.
 */
function tacobout_flush_rewrite_rules() {
	$version_flag = 'tacobout_rewrite_rules_flushed_v2';
	if ( ! get_option( $version_flag ) ) {
		flush_rewrite_rules();
		update_option( $version_flag, true );
	}
}
add_action( 'init', 'tacobout_flush_rewrite_rules', 999 );
