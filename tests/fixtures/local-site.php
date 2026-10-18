<?php
/** Disposable browser fixtures. Run only with wp eval-file on a loopback review site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Fixtures may only run on a loopback-only local WordPress.' );
}
if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || ! has_filter( 'pre_http_request' ) ) {
	throw new RuntimeException( 'Disable cron and install a blocking pre_http_request filter before seeding fixtures.' );
}
// The disposable site must block external HTTP; no real federation credentials belong here.
$theme = get_template_directory();
$assets = dirname( $theme ) . '/tacobout-proposal/assets/';
// A symlinked theme may resolve differently; accept a supplied absolute asset directory.
$assets = rtrim( getenv( 'TACOBOUT_FIXTURE_ASSETS' ) ?: $assets, '/' ) . '/';
if ( ! is_file( $assets . 'tubi.jpg' ) ) { throw new RuntimeException( 'Set TACOBOUT_FIXTURE_ASSETS to the local review assets directory.' ); }
require_once ABSPATH . 'wp-admin/includes/image.php';
$uploads = wp_upload_dir();
$media = array();
foreach ( array( 'tubi.jpg', 'revry.webp', 'kitchen.jpg', 'slay.jpg', 'mark.png', 'tone.wav' ) as $name ) {
	$source = $assets . $name;
	if ( ! is_file( $source ) ) { continue; }
	copy( $source, $uploads['path'] . '/' . $name );
	$id = wp_insert_attachment( array( 'post_title' => 'Local fixture ' . $name, 'post_status' => 'inherit', 'post_mime_type' => wp_check_filetype( $name )['type'] ), $uploads['path'] . '/' . $name );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $uploads['path'] . '/' . $name ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Local review artwork: ' . $name );
	$media[$name] = array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
}
set_theme_mod( 'custom_logo', $media['mark.png']['id'] );
$tv = wp_insert_term( 'TV', 'category', array( 'slug' => 'tv' ) );
$food = wp_insert_term( 'Food', 'category', array( 'slug' => 'food' ) );
$tv = is_wp_error( $tv ) ? (int) get_term_by( 'slug', 'tv', 'category' )->term_id : $tv['term_id'];
$food = is_wp_error( $food ) ? (int) get_term_by( 'slug', 'food', 'category' )->term_id : $food['term_id'];
$paragraph = '<p>Tubi has reached the point where “it is on Tubi” is not an explanation of what kind of movie you are dealing with. Some stories deserve a deep dive; others a quick discovery. This local fixture checks the reading measure, source links, captions, and full conversation without changing a published article.</p>';
$essay = $paragraph . '<h2>Slay: drag queens versus vampires</h2>' . $paragraph . '<figure><img src="' . esc_url( $media['slay.jpg']['url'] ) . '" alt="Slay promotional artwork"><figcaption>Promotional image, from the existing public blog.</figcaption></figure><blockquote>A streaming service being free does not prevent it from having a personality.</blockquote>' . $paragraph;
$cases = array(
	array( 'standard', 'Tubi Originals to Watch Free: Slay, Lowlifes, and The Z-Suite', $essay, 'tubi.jpg' ),
	array( 'link', 'A small corner of the open web, worth keeping', '<p>A concise find with a little context.</p><p><a href="https://wordpress.org/">Visit WordPress.org</a></p>', null ),
	array( 'video', 'A little television, between the long reads', '<figure class="wp-block-video"><video controls preload="none" poster="' . $media['revry.webp']['url'] . '" aria-label="Local video fixture"></video><figcaption>Local player fixture; source attribution remains beside the player.</figcaption></figure><p>A concise video post. <a href="https://www.revry.tv/">Official source</a>.</p>', 'tubi.jpg' ),
	array( 'status', 'Status fixture', '<p>Some stories deserve a deep dive. Others are best appreciated with a raised eyebrow.</p>', null ),
	array( 'standard', 'Watch Revry Free: Drag Competitions, Queer Comedy, and More', $essay, 'revry.webp' ),
	array( 'audio', 'A short dispatch from the feed', '<audio controls preload="none" src="' . $media['tone.wav']['url'] . '"></audio><p>Local three-second tone.</p><details><summary>Transcript</summary><p>A generated test tone, not a podcast.</p></details>', null ),
	array( 'aside', 'Aside fixture', str_repeat( $paragraph, 4 ), null ),
	array( 'image', 'Image fixture', '<figure><img src="' . $media['kitchen.jpg']['url'] . '" alt="A kitchen from public promotional artwork"><figcaption>Image caption and source.</figcaption></figure>', 'kitchen.jpg' ),
	array( 'quote', 'Quote fixture', '<blockquote><p>Plenty to talk about.</p><cite>Publication tagline</cite></blockquote>', null ),
	array( 'gallery', 'Gallery fixture', '<div class="wp-block-gallery"><figure><img src="' . $media['revry.webp']['url'] . '" alt="Unconventional artwork"><figcaption>First image</figcaption></figure><figure><img src="' . $media['kitchen.jpg']['url'] . '" alt="Kitchen artwork"><figcaption>Second image</figcaption></figure></div>', null ),
	array( 'chat', 'A conversation between friends', '<p><strong>Schweg:</strong> An essay or a quick link?</p><p><strong>A friend:</strong> There’s room for both.</p>', null ),
	array( 'standard', 'Tastemade Free Streaming: Cooking Shows Beyond the Reruns', $essay, 'kitchen.jpg' ),
);
$ids = array();
foreach ( $cases as $index => $case ) {
	$existing = get_posts( array( 'title' => $case[1], 'numberposts' => 1, 'post_type' => 'post', 'post_status' => 'any' ) );
	$id = wp_insert_post( array( 'ID' => $existing ? $existing[0]->ID : 0, 'post_title' => $case[1], 'post_content' => $case[2], 'post_excerpt' => 'Commentary, discoveries, and a well-earned side-eye. A local fixture for the mixed-format editorial feed.', 'post_status' => 'publish', 'post_author' => 1, 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2026-10-16 12:00:00' ) - $index * 3600 ), 'comment_status' => 'open', 'post_category' => array( $index === 11 ? $food : $tv ) ) );
	set_post_format( $id, $case[0] );
	if ( $case[3] ) { set_post_thumbnail( $id, $media[$case[3]]['id'] ); }
	wp_set_post_tags( $id, array( 'Stuff You Can Watch Free' ) );
	$ids[$case[0]] = $id;
}
wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'post_content' => '<h2>Culture, technology, and the internet.</h2><p>An independent publication with commentary, discoveries, and the occasional well-earned side-eye.</p><h2>Schweg and Friends</h2><p>Curiosity, a point of view, and a healthy tolerance for the absurd.</p>', 'post_status' => 'publish' ) );
wp_update_user( array( 'ID' => 1, 'display_name' => 'Schweg', 'user_nicename' => 'schwegler' ) );
$native = wp_insert_comment( array( 'comment_post_ID' => $ids['standard'], 'comment_author' => 'Local reader', 'comment_content' => 'A native comment in the local review fixture.', 'comment_approved' => 1 ) );
foreach ( array( 'activitypub', 'atproto' ) as $protocol ) {
	$id = wp_insert_comment( array( 'comment_post_ID' => $ids['standard'], 'comment_author' => 'Federated reader', 'comment_author_url' => 'https://example.invalid/reader', 'comment_content' => 'A locally imported reply fixture.', 'comment_approved' => 1 ) );
	add_comment_meta( $id, 'protocol', $protocol ); add_comment_meta( $id, 'source_id', 'https://example.invalid/' . $protocol );
}
WP_CLI::log( wp_json_encode( $ids ) );
