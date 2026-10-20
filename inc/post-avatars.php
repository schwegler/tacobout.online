<?php
/** Compact native avatar blocks for index metadata and the single-post byline. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Limit only these opt-in avatar blocks; profile and comment avatars stay native. */
function tacobout_render_metadata_avatar( $content, $parsed, $block ) {
	$classes = preg_split( '/\s+/', $parsed['attrs']['className'] ?? '' );
	$card    = in_array( 'tacobout-card-author-avatar', $classes, true );
	$byline  = in_array( 'tacobout-byline-avatar', $classes, true );
	if ( ! $card && ! $byline ) {
		return $content;
	}
	if ( is_author() || ! get_option( 'show_avatars' ) ) {
		return '';
	}
	if ( $card && ! ( is_home() || is_front_page() || is_archive() || is_search() ) ) {
		return '';
	}
	if ( $byline && ! is_single() ) {
		return '';
	}
	$post_id = (int) ( $block->context['postId'] ?? 0 );
	$author  = $post_id ? (int) get_post_field( 'post_author', $post_id ) : 0;
	if ( ! $author ) {
		return '';
	}
	// Use the URL/data API, including explicit AP icon precedence, even if an HTML
	// avatar plugin supplied different markup/srcset to the native avatar block.
	$url = get_avatar_url( $author, array( 'size' => 48 ) );
	if ( ! $url ) {
		return '';
	}
	$tags = new WP_HTML_Tag_Processor( $content );
	if ( ! $tags->next_tag( 'IMG' ) ) {
		return '';
	}
	$tags->set_attribute( 'src', $url );
	$tags->remove_attribute( 'srcset' );
	$tags->set_attribute( 'alt', '' );
	$tags->set_attribute( 'width', '24' );
	$tags->set_attribute( 'height', '24' );
	$tags->set_attribute( 'loading', 'lazy' );
	$tags->set_attribute( 'decoding', 'async' );
	if ( $byline ) {
		$tags->set_attribute( 'aria-hidden', 'true' );
	}
	$content = $tags->get_updated_html();
	if ( $card ) {
		$tags = new WP_HTML_Tag_Processor( $content );
		if ( $tags->next_tag( 'A' ) ) {
			$tags->set_attribute( 'aria-label', sprintf( __( 'Posts by %s', 'tacobout' ), get_the_author_meta( 'display_name', $author ) ) );
		}
		$content = $tags->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/avatar', 'tacobout_render_metadata_avatar', 20, 3 );
