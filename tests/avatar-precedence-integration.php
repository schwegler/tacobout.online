<?php
/** Read-only integration assertions on the existing disposable local fixture. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) || ! has_filter( 'pre_http_request' ) ) {
	throw new RuntimeException( 'Requires loopback WordPress with outbound HTTP blocked.' );
}
$user = get_users( array( 'number' => 1, 'orderby' => 'ID' ) )[0];
$expected = tacobout_explicit_activitypub_icon( $user );
if ( ! $expected ) { throw new RuntimeException( 'Seed an explicit icon on the disposable fixture first.' ); }
// Simulate a fallback plugin that short-circuits before WordPress calculates URLs.
$competing = function ( $args ) { $args['url'] = 'https://gravatar.invalid/robohash'; return $args; };
add_filter( 'pre_get_avatar_data', $competing, 999 );
try {
	foreach ( array( $user->ID, $user, $user->user_email ) as $identity ) {
		$data = get_avatar_data( $identity, array( 'size' => 72 ) );
		if ( $expected !== get_avatar_url( $identity ) || $expected !== $data['url'] || ! $data['found_avatar'] || 72 !== $data['size'] ) {
			throw new RuntimeException( 'Avatar URL/data did not retain the explicit icon.' );
		}
	}
	$profile = apply_filters( 'webfinger_data', array(), 'acct:' . $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( is_wp_error( $profile ) ) { throw new RuntimeException( $profile->get_error_message() ); }
	$links = array_values( array_filter( $profile['links'] ?? array(), fn( $link ) => 'http://webfinger.net/rel/avatar' === ( $link['rel'] ?? '' ) ) );
	if ( 1 !== count( $links ) || $expected !== $links[0]['href'] ) { throw new RuntimeException( 'Native WebFinger avatar does not match explicit icon.' ); }
	$default = get_avatar_data( $user->ID, array( 'force_default' => true ) );
	if ( 'https://gravatar.invalid/robohash' !== $default['url'] ) { throw new RuntimeException( 'Explicit force_default was overridden.' ); }
} finally {
	remove_filter( 'pre_get_avatar_data', $competing, 999 );
}
WP_CLI::success( 'Native avatar URL/data and WebFinger use the explicit icon despite competing pre-data fallback; force_default preserved.' );
