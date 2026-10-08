<?php
/**
 * Approved local engagement, independent of plugin-filtered post.comment_count.
 *
 * @package Tacobout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Normalize grouped, deduplicated local records; never claim global coverage. */
function tacobout_normalize_engagement( $rows ) {
	$data = array(
		'comments'       => 0,
		'other_comments' => 0,
		'replies'        => 0,
		'likes'          => 0,
		'reposts'        => 0,
		'quotes'         => 0,
		'activitypub'    => array(
			'replies' => 0,
			'likes'   => 0,
			'reposts' => 0,
			'quotes'  => 0,
		),
		'atproto'        => array(
			'replies' => 0,
			'likes'   => 0,
			'reposts' => 0,
			'quotes'  => 0,
		),
		'total'          => 0,
		'updated_at'     => null,
		'last_synced_at' => null,
		'calculated_at'  => gmdate( 'c' ),
		'is_stale'       => false,
		'is_partial'     => true,
		'source'         => 'approved_local_comments',
		'coverage'       => array(
			'activitypub'    => 'locally_received_only',
			'atproto'        => 'locally_imported_only',
			'atproto_quotes' => 'no_verified_import_lane',
		),
	);
	foreach ( $rows as $row ) {
		$type     = $row->comment_type;
		$protocol = $row->protocol;
		$count    = max( 0, (int) $row->total );
		$is_reply = in_array( $type, array( '', 'comment' ), true );
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			$data['other_comments'] += $count;
		} elseif ( in_array( $protocol, array( 'activitypub', 'atproto' ), true ) ) {
			$field = $is_reply ? 'replies' : ( array(
				'like'   => 'likes',
				'repost' => 'reposts',
				'quote'  => 'quotes',
			)[ $type ] ?? null );
			if ( $field ) {
				$data[ $protocol ][ $field ] += $count;
			}
		} elseif ( $is_reply ) {
			$data[ '' === $protocol ? 'comments' : 'other_comments' ] += $count;
		}
	}
	foreach ( array( 'replies', 'likes', 'reposts', 'quotes' ) as $field ) {
		$data[ $field ] = $data['activitypub'][ $field ] + $data['atproto'][ $field ];
	}
	$data['total'] = $data['comments'] + $data['other_comments'] + $data['replies'] + $data['likes'] + $data['reposts'] + $data['quotes'];
	return $data;
}

/** One aggregate per cache miss: no bodies, no remote requests. */
function tacobout_get_engagement( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return tacobout_normalize_engagement( array() );
	}
	$key        = 'post_' . $post_id;
	$generation = wp_cache_get_last_changed( 'comment' );
	$cached     = wp_cache_get( $key, 'tacobout_engagement' );
	if ( is_array( $cached ) && $generation === $cached['generation'] && time() - $cached['time'] < 300 ) {
		return $cached['data'];
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Cached aggregate; core count APIs exclude reactions.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT BINARY CASE WHEN c.comment_type = '' THEN 'comment' ELSE c.comment_type END AS comment_type,
			BINARY COALESCE(p.meta_value, '') AS protocol,
			COUNT(DISTINCT BINARY CASE
				WHEN p.meta_value IN ('activitypub', 'atproto') AND s.meta_value <> ''
				THEN CONCAT('remote:', s.meta_value)
				ELSE CONCAT('local:', c.comment_ID) END) AS total
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->commentmeta} p ON p.meta_id =
				(SELECT MIN(pm.meta_id) FROM {$wpdb->commentmeta} pm WHERE pm.comment_id = c.comment_ID AND pm.meta_key = 'protocol')
			LEFT JOIN {$wpdb->commentmeta} s ON s.meta_id =
				(SELECT MIN(sm.meta_id) FROM {$wpdb->commentmeta} sm WHERE sm.comment_id = c.comment_ID AND sm.meta_key = 'source_id')
			WHERE c.comment_post_ID = %d AND c.comment_approved = '1'
			AND c.comment_type IN ('', 'comment', 'pingback', 'trackback', 'like', 'repost', 'quote')
			GROUP BY BINARY CASE WHEN c.comment_type = '' THEN 'comment' ELSE c.comment_type END, BINARY COALESCE(p.meta_value, '')",
			$post_id
		)
	);
	if ( ! is_array( $rows ) || $wpdb->last_error ) {
		$data             = is_array( $cached ) ? $cached['data'] : tacobout_normalize_engagement( array() );
		$data['is_stale'] = true;
		$data['source']   = 'local_query_failed';
		return $data;
	}
	$data = tacobout_normalize_engagement( $rows );
	wp_cache_set(
		$key,
		array(
			'generation' => $generation,
			'time'       => time(),
			'data'       => $data,
		),
		'tacobout_engagement',
		300
	);
	return $data;
}

/** Compatibility integer: approved local engagement, not global network totals. */
function tacobout_get_interaction_count( $post_id ) {
	return tacobout_get_engagement( $post_id )['total'];
}

/** One cache key/group used by all invalidation paths. */
function tacobout_invalidate_interaction_count_cache( $post_id ) {
	wp_cache_delete( 'post_' . absint( $post_id ), 'tacobout_engagement' );
}
add_action( 'clean_post_cache', 'tacobout_invalidate_interaction_count_cache' );
add_action( 'wp_update_comment_count', 'tacobout_invalidate_interaction_count_cache' );

/** Use supplied object after deletion, when the row can no longer be retrieved. */
function tacobout_clear_interaction_count_cache( $comment_id, $comment_object = null ) {
	$comment = is_object( $comment_object ) ? $comment_object : get_comment( $comment_id );
	if ( $comment ) {
		tacobout_invalidate_interaction_count_cache( $comment->comment_post_ID );
	}
}
add_action( 'wp_insert_comment', 'tacobout_clear_interaction_count_cache', 20, 2 );
add_action( 'edit_comment', 'tacobout_clear_interaction_count_cache', 20, 1 );
add_action( 'deleted_comment', 'tacobout_clear_interaction_count_cache', 20, 2 );
add_action(
	'transition_comment_status',
	function ( $new_status, $old_status, $comment ) {
		tacobout_clear_interaction_count_cache( $comment->comment_ID, $comment );
	},
	20,
	3
);

/** Imports may write protocol/source identity AFTER wp_insert_comment. */
function tacobout_engagement_comment_meta_changed( $meta_id, $comment_id, $key ) {
	if ( in_array( $key, array( 'protocol', 'source_id' ), true ) ) {
		tacobout_clear_interaction_count_cache( $comment_id );
	}
}
foreach ( array( 'added_comment_meta', 'updated_comment_meta', 'deleted_comment_meta' ) as $hook ) {
	add_action( $hook, 'tacobout_engagement_comment_meta_changed', 20, 3 );
}
add_action( 'atmosphere_reaction_synced', 'tacobout_clear_interaction_count_cache', 20, 1 );

/** Private CLI inspection; refresh only this post's local snapshot. */
function tacobout_engagement_diagnostics( $post_id ) {
	return array(
		'post_id'                      => absint( $post_id ),
		'permalink'                    => get_permalink( $post_id ),
		'activitypub_object_candidate' => get_permalink( $post_id ),
		'atproto_uri'                  => get_post_meta( $post_id, '_atmosphere_bsky_uri', true ),
		'atmosphere_next_sync'         => wp_next_scheduled( 'atmosphere_sync_reactions' ),
		'atmosphere_next_backfill'     => wp_next_scheduled( 'atmosphere_backfill_replies' ),
		'engagement'                   => tacobout_get_engagement( $post_id ),
	);
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'tacobout engagement',
		function ( $args, $assoc_args ) {
			$post_id = absint( $args[0] ?? 0 );
			if ( ! $post_id || ! get_post( $post_id ) ) {
				WP_CLI::error( 'Supply an existing post ID.' );
			}
			if ( isset( $assoc_args['refresh'] ) ) {
				tacobout_invalidate_interaction_count_cache( $post_id );
			}
			WP_CLI::line( wp_json_encode( tacobout_engagement_diagnostics( $post_id ), JSON_PRETTY_PRINT ) );
		}
	);
}
