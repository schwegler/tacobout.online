<?php
/** Bridge explicit ActivityPub user icons into WordPress's final avatar data. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve local identities only; never replace a remote/anonymous comment avatar. */
function tacobout_avatar_user_id( $identity ) {
	if ( $identity instanceof WP_User ) {
		return (int) $identity->ID;
	}
	if ( $identity instanceof WP_Post ) {
		return (int) $identity->post_author;
	}
	if ( $identity instanceof WP_Comment ) {
		if ( ! is_avatar_comment_type( get_comment_type( $identity ) ) ) {
			return 0;
		}
		return (int) $identity->user_id;
	}
	if ( is_int( $identity ) || ( is_string( $identity ) && ctype_digit( $identity ) ) ) {
		return max( 0, (int) $identity );
	}
	if ( is_string( $identity ) && is_email( $identity ) && ! preg_match( '/@(?:md5|sha256)\.gravatar\.com$/i', $identity ) ) {
		$user = get_user_by( 'email', $identity );
		return $user ? (int) $user->ID : 0;
	}
	return 0;
}

/** Read the explicit attachment directly, never actor->get_icon()/get_avatar_url(). */
function tacobout_explicit_activitypub_icon( $identity ) {
	static $resolving = false;
	if ( $resolving ) {
		return '';
	}
	$resolving = true;
	try {
		$user_id = tacobout_avatar_user_id( $identity );
		if ( $user_id <= 0 ) {
			return '';
		}
		$icon = get_user_option( 'activitypub_icon', $user_id );
		if ( ! ( is_int( $icon ) || ( is_string( $icon ) && ctype_digit( $icon ) ) ) || (int) $icon <= 0 ) {
			return '';
		}
		$attachment = get_post( (int) $icon );
		if ( ! $attachment || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status || ! wp_attachment_is_image( (int) $icon ) ) {
			return '';
		}
		$url = wp_get_attachment_url( (int) $icon );
		return is_string( $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
	} finally {
		$resolving = false;
	}
}

/** Final data hook also covers pre_get_avatar_data short-circuits and URL callers. */
function tacobout_explicit_icon_avatar_data( $args, $identity ) {
	if ( ! empty( $args['force_default'] ) ) {
		return $args;
	}
	$url = tacobout_explicit_activitypub_icon( $identity );
	if ( $url ) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}
	return $args;
}
add_filter( 'get_avatar_data', 'tacobout_explicit_icon_avatar_data', PHP_INT_MAX, 2 );
