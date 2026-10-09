<?php
/**
 * Public Trove item cards for post content. No account credentials are used.
 *
 * @package Tacobout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accept only public item URLs on the collection site, never profile/login URLs.
 *
 * @param string $url Candidate item URL.
 * @return string Canonical URL, or an empty string for unsupported URLs.
 */
function tacobout_trove_item_url( $url ) {
	$parts = wp_parse_url( trim( (string) $url ) );
	if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || 'trove.schweg.xyz' !== strtolower( $parts['host'] ?? '' ) ) {
		return '';
	}
	foreach ( array( 'user', 'pass', 'port', 'query' ) as $component ) {
		if ( isset( $parts[ $component ] ) ) {
			return '';
		}
	}
	$path = $parts['path'] ?? '';
	if ( ! preg_match( '#^/(movies|albums|comics|tv_shows|tv_episodes|video_games|books)/[1-9][0-9]*(?:-[a-z0-9]+(?:-[a-z0-9]+)*)?/?$#', $path ) ) {
		return '';
	}
	return 'https://trove.schweg.xyz' . rtrim( $path, '/' );
}

/**
 * Read Open Graph text without executing page scripts or loading external XML.
 *
 * @param string $html Public page HTML.
 * @return array Open Graph fields.
 */
function tacobout_trove_parse_metadata( $html ) {
	$metadata = array();
	if ( ! class_exists( 'DOMDocument' ) || '' === trim( $html ) ) {
		return $metadata;
	}
	$previous = libxml_use_internal_errors( true );
	$document = new DOMDocument();
	$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( ! $loaded ) {
		return $metadata;
	}
	foreach ( $document->getElementsByTagName( 'meta' ) as $meta ) {
		$property = strtolower( $meta->getAttribute( 'property' ) );
		if ( in_array( $property, array( 'og:title', 'og:description', 'og:image' ), true ) && ! isset( $metadata[ $property ] ) ) {
			$metadata[ $property ] = $meta->getAttribute( 'content' );
		}
	}
	return $metadata;
}

/**
 * Cache public metadata for six hours; failures get a short retry delay.
 *
 * @param string $url Public item URL.
 * @return array Cached or fetched Open Graph fields.
 */
function tacobout_trove_metadata( $url ) {
	$url = tacobout_trove_item_url( $url );
	if ( '' === $url ) {
		return array();
	}
	$key    = 'tacobout_trove_' . md5( $url );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 3,
			'redirection'         => 0,
			'limit_response_size' => 262144,
			'headers'             => array( 'Accept' => 'text/html' ),
		)
	);
	$metadata = array();
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$metadata = tacobout_trove_parse_metadata( wp_remote_retrieve_body( $response ) );
	}
	// A login page or missing item must not become a misleading collection card.
	if ( empty( $metadata['og:title'] ) ) {
		$metadata = array();
	}
	set_transient( $key, $metadata, empty( $metadata ) ? 300 : 6 * HOUR_IN_SECONDS );
	return $metadata;
}

/**
 * Render [trove url="https://trove.schweg.xyz/books/13" note="My thoughts…"].
 *
 * @param array $attributes Shortcode attributes.
 * @return string Escaped card markup, or empty for an unsupported URL.
 */
function tacobout_trove_shortcode( $attributes ) {
	$attributes = shortcode_atts(
		array(
			'url'   => '',
			'title' => '',
			'note'  => '',
		),
		$attributes,
		'trove'
	);
	$url        = tacobout_trove_item_url( $attributes['url'] );
	if ( '' === $url ) {
		return '';
	}
	$metadata    = tacobout_trove_metadata( $url );
	$title       = '' !== $attributes['title'] ? $attributes['title'] : ( $metadata['og:title'] ?? __( 'View this item on Trove', 'tacobout' ) );
	$title       = preg_replace( '/\s*\|\s*Trove\s*$/u', '', $title );
	$description = $metadata['og:description'] ?? '';
	$image       = $metadata['og:image'] ?? '';
	$image_parts = wp_parse_url( $image );
	if ( ! is_array( $image_parts ) || 'https' !== ( $image_parts['scheme'] ?? '' ) || empty( $image_parts['host'] ) || isset( $image_parts['user'] ) || isset( $image_parts['pass'] ) ) {
		$image = '';
	}
	$html = '<a class="tacobout-trove-card" href="' . esc_url( $url ) . '">';
	if ( '' !== $image ) {
		$html .= '<img class="tacobout-trove-card__cover" src="' . esc_url( $image ) . '" alt="" width="96" height="128" loading="lazy" decoding="async" referrerpolicy="no-referrer" />';
	}
	$html .= '<span class="tacobout-trove-card__body"><span class="tacobout-trove-card__label">' . esc_html__( 'From my media collection', 'tacobout' ) . '</span>';
	$html .= '<span class="tacobout-trove-card__title">' . esc_html( $title ) . '</span>';
	if ( '' !== $description ) {
		$html .= '<span class="tacobout-trove-card__description">' . esc_html( $description ) . '</span>';
	}
	if ( '' !== $attributes['note'] ) {
		$html .= '<span class="tacobout-trove-card__note">' . esc_html( $attributes['note'] ) . '</span>';
	}
	$html .= '<span class="tacobout-trove-card__cta">' . esc_html__( 'View on Trove', 'tacobout' ) . ' <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M7 17 17 7M7 7h10v10" /></svg></span></span></a>';
	return $html;
}

/**
 * Support both a Shortcode block and a Trove URL pasted on its own line.
 */
function tacobout_register_trove_embeds() {
	add_shortcode( 'trove', 'tacobout_trove_shortcode' );
	wp_embed_register_handler(
		'tacobout-trove',
		'#^https://trove\.schweg\.xyz/(?:movies|albums|comics|tv_shows|tv_episodes|video_games|books)/[1-9][0-9]*(?:-[a-z0-9]+(?:-[a-z0-9]+)*)?/?(?:\#[^\s]*)?$#i',
		function ( $matches, $attributes, $url ) {
			return tacobout_trove_shortcode( array( 'url' => $url ) );
		}
	);
}
add_action( 'init', 'tacobout_register_trove_embeds' );
