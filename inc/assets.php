<?php
/** Content-addressed frontend assets survive stripped version query strings. */

/**
 * Only replace this parent theme's known assets. Child-theme overrides and
 * WordPress/plugin/CDN assets keep their own loading and versioning behavior.
 */
function tacobout_fingerprint_asset_src( $src ) {
	static $manifest = null;
	if ( null === $manifest ) {
		$manifest_path = __DIR__ . '/../asset-manifest.json';
		$manifest = is_readable( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : array();
	}

	$base = rtrim( get_template_directory_uri(), '/' ) . '/';
	$path = explode( '?', $src, 2 )[0];
	if ( ! str_starts_with( $path, $base ) ) {
		return $src;
	}
	$file = substr( $path, strlen( $base ) );
	if ( ! isset( $manifest[ $file ] ) || ! is_file( __DIR__ . '/../' . $manifest[ $file ] ) ) {
		return $src;
	}

	// The filename carries the version; a query is neither needed nor relied on.
	return $base . $manifest[ $file ];
}
add_filter( 'style_loader_src', 'tacobout_fingerprint_asset_src', PHP_INT_MAX );
add_filter( 'script_loader_src', 'tacobout_fingerprint_asset_src', PHP_INT_MAX );
