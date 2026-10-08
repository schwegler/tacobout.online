<?php
/** Run ONLY on a disposable WordPress database: wp eval-file tests/engagement-integration.php */
require_once dirname( __DIR__ ) . '/functions.php';


function engagement_assert( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
    }
    WP_CLI::line( 'PASS ' . $label );
}
add_filter( 'pre_http_request', function () { throw new RuntimeException( 'Unexpected remote HTTP request' ); } );
$post = wp_insert_post( array( 'post_title' => 'Disposable engagement audit', 'post_status' => 'publish' ) );
$other = wp_insert_post( array( 'post_title' => 'Move target', 'post_status' => 'publish' ) );
$insert = function ( $type = 'comment', $protocol = '', $source = '', $status = 1 ) use ( $post ) {
    $id = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_type' => $type, 'comment_approved' => $status, 'comment_content' => 'fixture' ) );
    // Simulate Atmosphere: metadata is written after insertion, and after a count read.
    tacobout_get_engagement( $post );
    if ( $protocol ) {
        update_comment_meta( $id, 'protocol', $protocol );
        update_comment_meta( $id, 'source_id', $source );
    }
    return $id;
};
try {
    engagement_assert( 0, tacobout_get_interaction_count( $post ), 'zero' );
    $native = $insert();
    $pending = $insert( 'comment', '', '', 0 );
    $insert( 'comment', '', '', 'spam' );
    $insert( 'note' );
    $insert( 'pingback' );
    engagement_assert( 2, tacobout_get_interaction_count( $post ), 'native, pingback; excludes pending, spam, private note' );
    $ap_reply = $insert( 'comment', 'activitypub', 'https://remote.test/reply/1' );
    $insert( 'like', 'activitypub', 'https://remote.test/like/1' );
    $insert( 'repost', 'activitypub', 'https://remote.test/repost/1' );
    $insert( 'quote', 'activitypub', 'https://remote.test/quote/1' );
    engagement_assert( 6, tacobout_get_interaction_count( $post ), 'ActivityPub local replies and reactions' );
    $insert( 'comment', 'atproto', 'at://did:plc:test/app.bsky.feed.post/reply' );
    $insert( 'like', 'atproto', 'at://did:plc:test/app.bsky.feed.like/1' );
    $repost = $insert( 'repost', 'atproto', 'at://did:plc:test/app.bsky.feed.repost/1' );
    engagement_assert( 9, tacobout_get_interaction_count( $post ), 'mixed approved engagement' );
    global $wpdb;
    $sql_mode = $wpdb->get_var( 'SELECT @@SESSION.sql_mode' );
    $wpdb->query( "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES'" );
    tacobout_invalidate_interaction_count_cache( $post );
    $strict = tacobout_get_engagement( $post );
    engagement_assert( false, $strict['is_stale'], 'aggregate works under ONLY_FULL_GROUP_BY' );
    engagement_assert( 9, $strict['total'], 'strict SQL aggregate total' );
    $wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $sql_mode ) );
    $duplicate = $insert( 'like', 'atproto', 'at://did:plc:test/app.bsky.feed.like/1' );
    // Duplicate meta rows must not multiply joins or counts.
    add_comment_meta( $duplicate, 'protocol', 'atproto' );
    add_comment_meta( $duplicate, 'source_id', 'at://did:plc:test/app.bsky.feed.like/1' );
    engagement_assert( 9, tacobout_get_interaction_count( $post ), 'duplicate source and metadata deduplicated' );
    add_filter( 'pre_wp_update_comment_count_now', function ( $new, $old, $id ) {
        return (int) get_comments( array( 'post_id' => $id, 'status' => 'approve', 'type__in' => array( 'comment', 'pingback', 'trackback' ), 'count' => true ) );
    }, 5, 3 );
    wp_update_comment_count_now( $post );
    engagement_assert( true, (int) get_post( $post )->comment_count < tacobout_get_interaction_count( $post ), 'filtered comment_count is not total engagement' );
    wp_delete_comment( $repost, true );
    engagement_assert( 8, tacobout_get_interaction_count( $post ), 'deletion invalidates after row removal' );
    wp_set_comment_status( $ap_reply, 'spam' );
    engagement_assert( 7, tacobout_get_interaction_count( $post ), 'status transition excludes spam' );
    wp_set_comment_status( $pending, 'approve' );
    engagement_assert( 8, tacobout_get_interaction_count( $post ), 'approval updates total' );
    wp_update_comment( array( 'comment_ID' => $native, 'comment_post_ID' => $other ) );
    engagement_assert( 7, tacobout_get_interaction_count( $post ), 'move invalidates old post through generation' );
    engagement_assert( 1, tacobout_get_interaction_count( $other ), 'move invalidates new post' );
    update_comment_meta( $duplicate, 'source_id', 'at://did:plc:test/app.bsky.feed.like/2' );
    engagement_assert( 8, tacobout_get_interaction_count( $post ), 'source identity edit changes deduplication' );
    do_action( 'atmosphere_reaction_synced', $duplicate, array(), $post, 'like' );
    engagement_assert( false, wp_cache_get( 'post_' . $post, 'tacobout_engagement' ), 'verified plugin completion hook invalidates' );
    // Simulate persistence ignoring TTL: explicit timestamp enforces the ceiling.
    tacobout_get_engagement( $post );
    $cached = wp_cache_get( 'post_' . $post, 'tacobout_engagement' );
    $cached['time'] -= 301;
    $cached['data']['total'] = 999;
    wp_cache_set( 'post_' . $post, $cached, 'tacobout_engagement' );
    engagement_assert( 8, tacobout_get_interaction_count( $post ), 'expired cache recomputes' );
    $request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post );
    $request->set_param( '_fields', 'id,interaction_count,engagement' );
    $response = rest_do_request( $request );
    engagement_assert( 200, $response->get_status(), 'real REST post response' );
    $data = $response->get_data();
    engagement_assert( 8, $data['interaction_count'], 'legacy REST integer' );
    engagement_assert( 8, $data['engagement']['total'], 'structured REST total' );
    engagement_assert( true, $data['engagement']['is_partial'], 'REST labels partial coverage' );
    engagement_assert( null, $data['engagement']['last_synced_at'], 'no fabricated sync time' );
    global $wpdb;
    $cached = wp_cache_get( 'post_' . $post, 'tacobout_engagement' );
    $cached['time'] -= 301;
    wp_cache_set( 'post_' . $post, $cached, 'tacobout_engagement' );
    $table = $wpdb->comments;
    $wpdb->comments = 'missing_fixture_table';
    $suppress = $wpdb->suppress_errors();
    $failed = tacobout_get_engagement( $post );
    $wpdb->comments = $table;
    $wpdb->suppress_errors( $suppress );
    $wpdb->last_error = '';
    engagement_assert( 8, $failed['total'], 'DB outage retains cached local count' );
    engagement_assert( true, $failed['is_stale'], 'DB outage reports stale data' );
    WP_CLI::success( 'Integration lifecycle passed; optional plugins inactive; zero remote HTTP requests.' );
} finally {
    wp_delete_post( $post, true );
    wp_delete_post( $other, true );
}
