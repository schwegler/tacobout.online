<?php
/** Inline feed experiences; single posts retain their complete authored content. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tacobout_feed_jump( $post_id ) {
	return '<p class="tacobout-card-jump"><a class="wp-block-post-excerpt__more-link" href="' . esc_url( get_permalink( $post_id ) ) . '">Read more<span class="screen-reader-text">: ' . esc_html( get_the_title( $post_id ) ) . '</span></a></p>';
}

/** Sanitize native image markup while retaining validated responsive candidates. */
function tacobout_link_preview_media( $html ) {
	$allowed = wp_kses_allowed_html( 'post' );
	// Core's post allowlist omits responsive image attributes added during rendering.
	$allowed['img']['sizes'] = true;
	$allowed['img']['srcset'] = true;
	$html = preg_replace_callback( '/\bsrcset="([^"]*)"/i', function ( $match ) {
		foreach ( explode( ',', html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) ) as $candidate ) {
			if ( ! preg_match( '~^\s*(?:https?://[^\s,]+|[^\s,:]+)(?:\s+\d+(?:\.\d+)?[wx])?\s*$~i', $candidate ) ) { return ''; }
		}
		return $match[0];
	}, $html );
	return wp_kses( $html, $allowed );
}

function tacobout_feed_preview( $html, $post_id, $format ) {
	if ( ! in_array( $format, array( 'image', 'video', 'link' ), true ) || ! class_exists( 'DOMDocument' ) ) { return $html; }
	// REST collections contain the full article; never select media below More.
	if ( 'link' === $format ) {
		$html = preg_split( '/<!--\s*more(?:\s[^>]*)?\s*-->|<(?:span|a)\b[^>]*(?:id=["\']more-\d+["\']|class=["\'][^"\']*\bmore-link\b)[^>]*>/i', $html, 2 )[0];
	}
	$dom = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors(); libxml_use_internal_errors( $previous );
	$xpath = new DOMXPath( $dom );
	$root = $xpath->query( '//div[contains(concat(" ",normalize-space(@class)," ")," wp-block-post-content ")]' )->item( 0 );
	if ( ! $root ) { return $html; }
	$source = '';
	foreach ( $xpath->query( './/a[@href]', $root ) as $link ) {
		$url = $link->getAttribute( 'href' );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { $source = $url; break; }
	}
	$selector = 'image' === $format ? './/img' : ( 'video' === $format ? './/video | .//iframe | .//object | .//embed' : './/blockquote | .//p[not(ancestor::figure) and normalize-space(.) != ""]' );
	$primary = $xpath->query( $selector, $root )->item( 0 );
	$content = '';
	if ( $primary && 'link' !== $format ) {
		// Keep the complete player/image wrapper, captions, tracks and source attributes.
		for ( $ancestor = $primary->parentNode; $ancestor && $ancestor !== $root; $ancestor = $ancestor->parentNode ) {
			if ( 'figure' === $ancestor->nodeName || ( $ancestor instanceof DOMElement && preg_match( '/(?:^|\s)(?:wp-block-embed|wp-video)(?:\s|$)/', $ancestor->getAttribute( 'class' ) ) ) ) { $primary = $ancestor; }
		}
		$content = $dom->saveHTML( $primary );
	} elseif ( $primary ) {
		$text = wp_trim_words( $primary->textContent, 55, '…' );
		$tag = 'blockquote' === $primary->nodeName ? 'blockquote' : 'p';
		$content = '<' . $tag . '>' . esc_html( $text ) . '</' . $tag . '>';
	}
	if ( 'link' === $format ) {
		// Retain one authored body image, never the featured-image block or a player.
		$image = $xpath->query( './/img[not(ancestor::*[contains(concat(" ",normalize-space(@class)," ")," wp-block-post-featured-image ")])]', $root )->item( 0 );
		if ( $image ) {
			$media = $image;
			for ( $ancestor = $image->parentNode; $ancestor && $ancestor !== $root; $ancestor = $ancestor->parentNode ) {
				if ( 'figure' === $ancestor->nodeName && 1 === $xpath->query( './/img', $ancestor )->length && 0 === $xpath->query( './/video | .//iframe | .//object | .//embed', $ancestor )->length ) { $media = $ancestor; break; }
			}
			$content = '<div class="tacobout-link-media">' . tacobout_link_preview_media( $dom->saveHTML( $media ) ) . '</div>' . $content;
		}
	}
	if ( $source ) {
		$host = preg_replace( '/^www\./', '', wp_parse_url( $source, PHP_URL_HOST ) );
		$content .= '<p class="tacobout-card-source"><a class="tacobout-link-source" href="' . esc_url( $source ) . '">Open source <span>' . esc_html( $host ) . ' ↗</span></a></p>';
	}
	$content .= tacobout_feed_jump( $post_id );
	while ( $root->firstChild ) { $root->removeChild( $root->firstChild ); }
	$root->setAttribute( 'data-feed-preview', $format );
	$wrapper = $dom->saveHTML( $root );
	return substr_replace( $wrapper, $content, strrpos( $wrapper, '</div>' ), 0 );
}

function tacobout_feed_card_content( $html, $parsed_block, $block ) {
	if ( ! isset( $block->context['queryId'], $block->context['postId'] ) ) { return $html; }
	$id = $block->context['postId'];
	return tacobout_feed_preview( $html, $id, tacobout_get_memoized_post_format( $id ) );
}
add_filter( 'render_block_core/post-content', 'tacobout_feed_card_content', 10, 3 );

/** Saved Query templates and native more markers use the same accessible jump label. */
function tacobout_feed_excerpt_jump( $html, $parsed_block, $block ) {
	if ( ! isset( $block->context['queryId'], $block->context['postId'] ) ) { return $html; }
	$id = $block->context['postId'];
	return preg_replace_callback( '/<a\b[^>]*class="[^"]*(?:wp-block-post-excerpt__more-link|more-link)[^"]*"[^>]*>.*?<\/a>/s', function ( $match ) use ( $id ) {
		preg_match( '/^<a\b[^>]*>/', $match[0], $opening );
		return $opening[0] . 'Read more<span class="screen-reader-text">: ' . esc_html( get_the_title( $id ) ) . '</span></a>';
	}, $html );
}
add_filter( 'render_block_core/post-excerpt', 'tacobout_feed_excerpt_jump', 20, 3 );

/** REST-loaded cards reuse already rendered content; never render embeds twice. */
function tacobout_feed_rest_preview( $response, $post, $request ) {
	if ( '/wp/v2/posts' !== $request->get_route() || ! isset( $response->data['content']['rendered'] ) ) { return $response; }
	$format = tacobout_get_memoized_post_format( $post->ID );
	if ( in_array( $format, array( 'image', 'video', 'link' ), true ) ) {
		$html = '<div class="wp-block-post-content">' . $response->data['content']['rendered'] . '</div>';
		$response->data['tacobout_feed_content'] = tacobout_feed_preview( $html, $post->ID, $format );
	}
	return $response;
}
add_filter( 'rest_prepare_post', 'tacobout_feed_rest_preview', 20, 3 );
add_filter( 'render_block_core/post-content', 'tacobout_feed_excerpt_jump', 30, 3 );
