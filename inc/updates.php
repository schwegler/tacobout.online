<?php
/** Public release discovery. WordPress owns downloads, installation and user consent. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TACOBOUT_UPDATE_URI = 'https://github.com/schwegler/tacobout.online';
const TACOBOUT_THEME_SLUG = 'tacobout.online-main';

/** Validate every identity and URL before offering a package to WordPress. */
function tacobout_release_update( $metadata, $theme_data, $stylesheet ) {
	global $wp_version;
	if ( TACOBOUT_THEME_SLUG !== $stylesheet || TACOBOUT_UPDATE_URI !== ( $theme_data['UpdateURI'] ?? '' ) || ! is_array( $metadata ) ) {
		return false;
	}
	$version = $metadata['version'] ?? '';
	if ( ! is_string( $version ) || ! preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version ) ) {
		return false;
	}
	$base = TACOBOUT_UPDATE_URI . '/releases/';
	if ( ( $metadata['schema'] ?? null ) !== 1 || ( $metadata['theme'] ?? '' ) !== TACOBOUT_THEME_SLUG || ( $metadata['id'] ?? '' ) !== TACOBOUT_UPDATE_URI || ( $metadata['url'] ?? '' ) !== $base . 'tag/v' . $version || ( $metadata['package'] ?? '' ) !== $base . 'download/v' . $version . '/theme.zip' ) {
		return false;
	}
	foreach ( array( 'requires', 'requires_php' ) as $key ) {
		if ( ! is_string( $metadata[$key] ?? null ) || ! preg_match( '/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D', $metadata[$key] ) ) {
			return false;
		}
	}
	if ( version_compare( $version, $theme_data['Version'] ?? '0', '<=' ) || version_compare( $wp_version, $metadata['requires'], '<' ) || version_compare( PHP_VERSION, $metadata['requires_php'], '<' ) ) {
		return false;
	}
	return array_intersect_key( $metadata, array_flip( array( 'id', 'theme', 'version', 'url', 'package', 'requires', 'requires_php' ) ) );
}

/** Called only by core's theme update check; never on ordinary frontend rendering. */
function tacobout_check_theme_release( $update, $theme_data, $stylesheet, $locales ) {
	if ( false !== $update || TACOBOUT_THEME_SLUG !== $stylesheet || TACOBOUT_UPDATE_URI !== ( $theme_data['UpdateURI'] ?? '' ) ) {
		return $update;
	}
	$key = 'tacobout_public_release_v1';
	$cached = get_site_transient( $key );
	if ( false === $cached ) {
		$response = wp_safe_remote_get( TACOBOUT_UPDATE_URI . '/releases/latest/download/tacobout-update.json', array( 'timeout' => 5, 'redirection' => 3, 'limit_response_size' => 16384, 'headers' => array( 'Accept' => 'application/json' ), 'cookies' => array() ) );
		$metadata = null;
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
		}
		$cached = array( 'metadata' => $metadata );
		// Cache failures too, so an unavailable release does not cause repeated requests.
		set_site_transient( $key, $cached, is_array( $metadata ) ? 3600 : 900 );
	}
	return tacobout_release_update( $cached['metadata'] ?? null, $theme_data, $stylesheet );
}
add_filter( 'update_themes_github.com', 'tacobout_check_theme_release', 10, 4 );

/** Honor the native Updates screen's explicit check before core runs its theme check. */
function tacobout_refresh_theme_release() {
	if ( empty( $_GET['force-check'] ) || ! current_user_can( 'update_themes' ) ) {
		return;
	}
	delete_site_transient( 'tacobout_public_release_v1' );
	// Core also throttles theme checks for one minute on this screen.
	delete_site_transient( 'update_themes' );
}
add_action( 'load-update-core.php', 'tacobout_refresh_theme_release', 5 );
