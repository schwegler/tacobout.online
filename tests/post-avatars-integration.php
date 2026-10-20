<?php
/** Render real native blocks against the existing local fixture; no account/content writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Loopback WordPress only.' );
}
$posts = get_posts( array( 'posts_per_page' => 1 ) );
if ( ! $posts ) { throw new RuntimeException( 'Seed a disposable local post first.' ); }
$post = $posts[0];
$original_query = $GLOBALS['wp_query'];
$render = function( $class, $linked ) use ( $post ) {
	return ( new WP_Block( array( 'blockName' => 'core/avatar', 'attrs' => array( 'size' => 24, 'isLink' => $linked, 'className' => $class ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $post->ID, 'postType' => 'post' ) ) )->render();
};
try {
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->is_home = true;
	$html = $render( 'tacobout-card-author-avatar', true );
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'A' ) || get_author_posts_url( $post->post_author ) !== $tags->get_attribute( 'href' ) || ! $tags->get_attribute( 'aria-label' ) ) { throw new RuntimeException( 'Card author link/label missing.' ); }
	if ( ! $tags->next_tag( 'IMG' ) || '' !== $tags->get_attribute( 'alt' ) || '24' !== (string) $tags->get_attribute( 'width' ) || get_avatar_url( $post->post_author, array( 'size' => 48 ) ) !== $tags->get_attribute( 'src' ) ) { throw new RuntimeException( 'Card image dimensions/URL/alt incorrect.' ); }
	$GLOBALS['wp_query']->is_home = false;
	$GLOBALS['wp_query']->is_author = true;
	$GLOBALS['wp_query']->is_archive = true;
	if ( '' !== $render( 'tacobout-card-author-avatar', true ) ) { throw new RuntimeException( 'Profile feed received a card avatar.' ); }
	$GLOBALS['wp_query']->is_author = false;
	$GLOBALS['wp_query']->is_archive = false;
	$GLOBALS['wp_query']->is_single = true;
	$html = $render( 'tacobout-byline-avatar', false );
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'IMG' ) || '' !== $tags->get_attribute( 'alt' ) || 'true' !== $tags->get_attribute( 'aria-hidden' ) || str_contains( $html, '<a ' ) ) { throw new RuntimeException( 'Byline avatar should be decorative without a duplicate author link.' ); }
} finally {
	$GLOBALS['wp_query'] = $original_query;
}
WP_CLI::success( 'Native per-post card URL, accessible link, dimensions, author exclusion and decorative unlinked byline avatar passed.' );
