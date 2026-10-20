<?php
/** Ordinary WordPress author presentation; federation remains owned by ActivityPub. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve only the queried author, never a post author from an inner query loop. */
function tacobout_profile_author() {
	$author = get_queried_object();
	return is_author() && $author instanceof WP_User ? $author : null;
}

/** Use ActivityPub's public actor model when the queried user is federation-enabled. */
function tacobout_profile_actor( $author ) {
	if ( ! $author || ! class_exists( '\Activitypub\Collection\Actors' ) ) {
		return null;
	}
	$actor = \Activitypub\Collection\Actors::get_by_id( $author->ID );
	return is_wp_error( $actor ) ? null : $actor;
}

/** Full-width banner. An explicitly configured plugin banner takes precedence. */
function tacobout_render_author_banner() {
	$author = tacobout_profile_author();
	if ( ! $author ) {
		return '';
	}
	$actor = tacobout_profile_actor( $author );
	$image = $actor ? $actor->get_image() : null;
	if ( ! empty( $image['url'] ) ) {
		return '<div class="tacobout-profile-banner has-user-banner"><img class="tacobout-profile-banner-image" src="' . esc_url( $image['url'] ) . '" alt="" decoding="async" /></div>';
	}
	$art = array(
		'schwegler' => 'assets/brand/schweg-mascot-420.png',
		'facet'     => 'assets/brand/author-facet.png',
		'telly'     => 'assets/brand/author-telly.png',
		'hitch'     => 'assets/brand/author-hitch.png',
	);
	$html = '<div class="tacobout-profile-banner"><div><p class="tacobout-profile-tagline">' . esc_html__( 'Plenty to talk about.', 'tacobout' ) . '</p><p>' . esc_html__( 'Culture, technology & the open web.', 'tacobout' ) . '</p></div>';
	if ( isset( $art[ $author->user_nicename ] ) ) {
		$html .= '<img class="tacobout-profile-art" src="' . esc_url( get_theme_file_uri( $art[ $author->user_nicename ] ) ) . '" alt="" decoding="async" />';
	}
	return $html . '</div>';
}

/** Keep the plugin's entire profile and modal intact, with a native-user fallback. */
function tacobout_render_author_profile() {
	$author = tacobout_profile_author();
	if ( ! $author ) {
		return '';
	}
	$actor = tacobout_profile_actor( $author );
	if ( $actor && WP_Block_Type_Registry::get_instance()->is_registered( 'activitypub/follow-me' ) ) {
		$handle = '@' . $actor->get_webfinger();
		$copy = '<button type="button" class="tacobout-profile-copy" data-profile-handle="' . esc_attr( $handle ) . '" data-copied-label="' . esc_attr__( 'Handle copied', 'tacobout' ) . '" data-failed-label="' . esc_attr__( 'Copy unavailable; use Follow to copy your handle.', 'tacobout' ) . '" hidden>' . esc_html__( 'Copy handle', 'tacobout' ) . '</button><span class="screen-reader-text tacobout-profile-copy-status" role="status"></span>';
		// The plugin accepts inner content for its follow button and attaches its own
		// Interactivity API actions. Keep our optional copy button outside that class.
		$profile = do_blocks( '<!-- wp:activitypub/follow-me {"selectedUser":"inherit","className":"is-style-profile"} --><!-- wp:html --><div class="wp-block-button"><a class="wp-element-button wp-block-button__link">' . esc_html__( 'Follow on the fediverse', 'tacobout' ) . '</a></div>' . $copy . '<!-- /wp:html --><!-- /wp:activitypub/follow-me -->' );
		if ( trim( $profile ) ) {
			return $profile;
		}
	}
	return '<div class="tacobout-profile-fallback">' . get_avatar( $author->ID, 100 ) . '<p class="tacobout-profile-name">' . esc_html( $author->display_name ) . '</p><div class="tacobout-profile-bio">' . wp_kses_post( wpautop( $author->description ) ) . '</div></div>';
}

add_action( 'init', function () {
	register_block_type( 'tacobout/author-banner', array( 'api_version' => 3, 'render_callback' => 'tacobout_render_author_banner' ) );
	register_block_type( 'tacobout/author-profile', array( 'api_version' => 3, 'render_callback' => 'tacobout_render_author_profile' ) );
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( is_author() ) {
		wp_enqueue_script( 'tacobout-author-profile', get_theme_file_uri( 'tacobout-author-profile.js' ), array(), wp_get_theme()->get( 'Version' ), true );
	}
} );
