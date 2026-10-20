<?php
/** Disposable local ActivityPub fixture. Never run against production. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) || ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || ! has_filter( 'pre_http_request' ) ) {
	throw new RuntimeException( 'Requires a loopback-only WordPress with cron and external HTTP disabled.' );
}
$user = get_users( array( 'number' => 1, 'orderby' => 'ID' ) )[0] ?? null;
if ( ! $user || ! class_exists( '\Activitypub\Collection\Actors' ) ) {
	throw new RuntimeException( 'Requires an existing disposable user and ActivityPub; creates no users.' );
}
wp_update_user( array( 'ID' => $user->ID, 'display_name' => 'schwegler', 'user_nicename' => 'schwegler', 'description' => '(he/any) not quite homo luminous (c. 1989) 🏳️‍🌈' ) );
require_once ABSPATH . 'wp-admin/includes/image.php';
$source = get_template_directory() . '/assets/brand/schweg-mascot-420.png';
$existing = get_posts( array( 'post_type' => 'attachment', 'title' => 'Author profile fixture avatar', 'posts_per_page' => 1, 'post_status' => 'inherit' ) );
if ( $existing ) {
	$avatar = $existing[0]->ID;
} else {
	$upload = wp_upload_bits( 'author-profile-avatar.png', null, file_get_contents( $source ) );
	$avatar = wp_insert_attachment( array( 'post_title' => 'Author profile fixture avatar', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $upload['file'] );
	wp_update_attachment_metadata( $avatar, wp_generate_attachment_metadata( $avatar, $upload['file'] ) );
}
update_user_option( $user->ID, 'activitypub_icon', $avatar );
$cases = array(
	array( 'candy corn has a history', '<!-- wp:paragraph --><p>A short dispatch from the feed. The original player controls stay available.</p><!-- /wp:paragraph --><!-- wp:video --><figure class="wp-block-video"><video controls preload="none" src="/wp-content/uploads/author-profile-clip.webm"></video></figure><!-- /wp:video -->', 'video' ),
	array( 'the halloween comics shelf has a serious woods problem', '<!-- wp:paragraph --><p>Zombies, missing memories, hungry forests, monster hunters, and pop-star gods. A review roundup from the shelf, with a little room to read.</p><!-- /wp:paragraph -->', 'standard' ),
	array( 'Reply fixture: keep the conversation attached', '<!-- wp:activitypub/reply {"url":"https://example.invalid/original-post"} /--><!-- wp:paragraph --><p>This local reply must disappear only in the Posts filter.</p><!-- /wp:paragraph -->', 'status' ),
);
for ( $i = 0; $i < 13; ++$i ) {
	$case = $cases[ $i % 3 ];
	$title = 'Profile fixture ' . $i . ': ' . $case[0];
	$existing = get_posts( array( 'title' => $title, 'post_status' => 'any', 'posts_per_page' => 1 ) );
	$id = wp_insert_post( array( 'ID' => $existing ? $existing[0]->ID : 0, 'post_type' => 'post', 'post_title' => $title, 'post_content' => $case[1], 'post_excerpt' => $i % 3 === 1 ? 'Five discoveries from the media shelf. Commentary, a little context, and a sensible reading measure.' : '', 'post_status' => 'publish', 'post_author' => $user->ID, 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2026-10-20 12:00:00' ) - $i * 3600 ) ) );
	set_post_format( $id, $case[2] );
}
WP_CLI::log( 'Fixture ready at ' . get_author_posts_url( $user->ID ) . '; outbound HTTP must stay blocked.' );
