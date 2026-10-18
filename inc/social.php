<?php
/** Presentation adapters for the installed plugins; no publishing or remote polling. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validate a recorded AT URI without inventing a remote mapping or making requests. */
function tacobout_atproto_record_url( $uri ) {
	if ( ! is_string( $uri ) || ! preg_match( '~^at://(did:[a-z0-9]+:[A-Za-z0-9._:%-]+)/app\.bsky\.feed\.post/([A-Za-z0-9._\~-]+)$~D', $uri, $parts ) ) {
		return '';
	}
	return 'https://bsky.app/profile/' . rawurlencode( $parts[1] ) . '/post/' . rawurlencode( $parts[2] );
}

/** Never add browser UI to RSS, ActivityPub objects or non-public posts. */
function tacobout_social_is_public_html( $post_id ) {
	return ! is_feed() && ! is_admin()
		&& ! ( function_exists( 'Activitypub\\is_activitypub_request' ) && \Activitypub\is_activitypub_request() )
		&& is_post_publicly_viewable( $post_id ) && ! post_password_required( $post_id );
}

/** No remote total is added to this deduplicated local snapshot. */
function tacobout_social_summary_shortcode() {
	$post_id = get_the_ID();
	if ( ! is_singular( 'post' ) || ! tacobout_social_is_public_html( $post_id ) ) {
		return '';
	}
	$data = tacobout_get_engagement( $post_id );
	$html = '<section class="tacobout-social-context" aria-label="' . esc_attr__( 'Open-web conversation', 'tacobout' ) . '"><h3>' . esc_html__( 'The conversation, here and elsewhere', 'tacobout' ) . '</h3>';
	if ( $data['is_stale'] || 'local_query_failed' === $data['source'] ) {
		$html .= '<p>' . esc_html__( 'Locally recorded interactions are temporarily unavailable. You can still read and reply below.', 'tacobout' ) . '</p>';
	} elseif ( 0 === $data['total'] ) {
		$html .= '<p>' . esc_html__( 'No approved interactions are recorded here yet. There may be more elsewhere.', 'tacobout' ) . '</p>';
	} else {
		$html .= '<details><summary>' . esc_html( sprintf( _n( '%s locally recorded interaction', '%s locally recorded interactions', $data['total'], 'tacobout' ), number_format_i18n( $data['total'] ) ) ) . '</summary><dl>';
		foreach ( array( 'comments' => __( 'Comments here', 'tacobout' ), 'activitypub' => 'ActivityPub', 'atproto' => 'AT Protocol', 'other_comments' => __( 'Other comments and references', 'tacobout' ) ) as $key => $label ) {
			$value = $data[$key];
			if ( is_array( $value ) ) {
				$pieces = array();
				foreach ( array( 'replies' => __( 'replies', 'tacobout' ), 'likes' => __( 'likes', 'tacobout' ), 'reposts' => __( 'reposts', 'tacobout' ), 'quotes' => __( 'quotes', 'tacobout' ) ) as $type => $type_label ) {
					if ( $value[$type] ) { $pieces[] = number_format_i18n( $value[$type] ) . ' ' . $type_label; }
				}
				$value = implode( ' · ', $pieces );
			} else {
				$value = $value ? number_format_i18n( $value ) : '';
			}
			if ( '' !== $value ) { $html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
		}
		$html .= '</dl></details>';
	}
	$html .= '<p class="tacobout-social-note">' . esc_html__( 'Approved records stored on this site, not a complete social-web total. Delivery, importing and moderation can take time; AT Protocol quote importing is not verified.', 'tacobout' ) . '</p>';
	// This is the plugin's stored record URI. No TID is allocated or publish attempted.
	if ( class_exists( 'Atmosphere\\Transformer\\Post' ) ) {
		$uri = get_post_meta( $post_id, \Atmosphere\Transformer\Post::META_URI, true );
		$url = tacobout_atproto_record_url( $uri );
		if ( $url ) {
			$html .= '<p><a class="tacobout-social-original" href="' . esc_url( $url ) . '" rel="syndication">' . esc_html__( 'Open this post on Bluesky', 'tacobout' ) . '</a></p>';
		}
	}
	if ( WP_Block_Type_Registry::get_instance()->is_registered( 'activitypub/follow-me' ) ) {
		$html .= do_blocks( '<!-- wp:activitypub/follow-me {"selectedUser":"blog","className":"is-style-button-only","buttonText":"Follow the publication"} /-->' );
	}
	return $html . '</section>';
}
add_action( 'init', function () { add_shortcode( 'tacobout_social_summary', 'tacobout_social_summary_shortcode' ); } );

/** Native ActivityPub actions also render the shared ATmosphere reactions. Do not duplicate them. */
function tacobout_native_social_actions( $block ) {
	if ( 'activitypub/reactions' === ( $block['blockName'] ?? '' ) && is_singular( 'post' ) && ! array_key_exists( 'showActions', $block['attrs'] ?? array() ) ) {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'activitypub/reactions' );
		if ( isset( $type->attributes['showActions'] ) ) {
			$block['attrs']['showActions'] = true;
		}
	}
	return $block;
}
add_filter( 'render_block_data', 'tacobout_native_social_actions' );

/** Retain the author's link, then attribute only approved, explicitly marked remote records. */
function tacobout_comment_protocol( $link, $author, $comment_id ) {
	if ( is_feed() || is_admin() || ( function_exists( 'Activitypub\\is_activitypub_request' ) && \Activitypub\is_activitypub_request() ) ) {
		return $link;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || '1' !== (string) $comment->comment_approved ) { return $link; }
	$protocol = get_comment_meta( $comment_id, 'protocol', true );
	$labels = array( 'activitypub' => 'ActivityPub', 'atproto' => 'AT Protocol' );
	if ( ! isset( $labels[$protocol] ) ) { return $link; }
	$source = get_comment_meta( $comment_id, 'source_id', true );
	$url = tacobout_atproto_record_url( $source );
	if ( ! $url ) { $url = tacobout_sidebar_image_url( $source ); }
	$label = esc_html( sprintf( __( 'via %s', 'tacobout' ), $labels[$protocol] ) );
	return $link . '<span class="tacobout-comment-protocol">' . ( $url ? '<a href="' . esc_url( $url ) . '" rel="nofollow ugc">' . $label . '</a>' : $label ) . '</span>';
}
add_filter( 'get_comment_author_link', 'tacobout_comment_protocol', 20, 3 );

/** Core block comments bypass get_comment_author_link(). */
add_filter( 'render_block_core/comment-author-name', function ( $content, $parsed, $block ) {
	$id = $block->context['commentId'] ?? 0;
	if ( ! $id ) { return $content; }
	$annotation = tacobout_comment_protocol( '', '', $id );
	return $annotation ? $content . $annotation : $content;
}, 20, 3 );

/** A relative social-link URL is coerced to https:///feed/ by core. */
add_filter( 'render_block_data', function ( $block ) {
	if ( 'core/social-link' === ( $block['blockName'] ?? '' ) && 'feed' === ( $block['attrs']['service'] ?? '' ) && '/feed/' === ( $block['attrs']['url'] ?? '' ) ) {
		$block['attrs']['url'] = home_url( '/feed/' );
	}
	return $block;
} );
