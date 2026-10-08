<?php

use Brain\Monkey\Functions;

class EngagementTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        Functions\when('absint')->alias(fn ($value) => abs((int) $value));
    }

    protected function tearDown(): void {
        unset($GLOBALS['wpdb']);
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_local_semantics_exclude_unknown_reactions_and_private_types(): void {
        $rows = [];
        foreach ([['comment', '', 3], ['comment', 'activitypub', 2], ['like', 'activitypub', 5],
            ['repost', 'activitypub', 1], ['quote', 'activitypub', 1], ['comment', 'atproto', 4],
            ['like', 'atproto', 6], ['repost', 'atproto', 2], ['pingback', '', 1],
            ['like', 'unknown', 99], ['note', '', 99]] as [$type, $protocol, $total]) {
            $rows[] = (object) ['comment_type' => $type, 'protocol' => $protocol, 'total' => $total];
        }
        $data = tacobout_normalize_engagement($rows);
        $this->assertSame(25, $data['total']);
        $this->assertSame(3, $data['comments']);
        $this->assertSame(6, $data['replies']);
        $this->assertSame(11, $data['likes']);
        $this->assertSame(3, $data['reposts']);
        $this->assertSame(1, $data['quotes']);
        $this->assertSame(0, $data['atproto']['quotes']);
        $this->assertTrue($data['is_partial']);
        $this->assertNull($data['last_synced_at']);
        $this->assertNull($data['updated_at']);
        $this->assertNotFalse(strtotime($data['calculated_at']));
    }

    public function test_zero_and_independent_protocols(): void {
        $this->assertSame(0, tacobout_get_interaction_count(0));
        foreach (['activitypub', 'atproto'] as $protocol) {
            $data = tacobout_normalize_engagement([(object) ['comment_type' => 'like', 'protocol' => $protocol, 'total' => 2]]);
            $this->assertSame(2, $data['total']);
            $this->assertSame(2, $data[$protocol]['likes']);
        }
    }

    private function query_with_cache($cached, $rows, string $error = ''): array {
        Functions\expect('wp_cache_get_last_changed')->once()->with('comment')->andReturn('new');
        Functions\expect('wp_cache_get')->once()->with('post_12', 'tacobout_engagement')->andReturn($cached);
        $db = \Mockery::mock();
        $db->comments = 'wp_comments';
        $db->commentmeta = 'wp_commentmeta';
        $db->last_error = $error;
        $db->shouldReceive('prepare')->once()->with(\Mockery::on(fn ($sql) => str_contains($sql, "c.comment_approved = '1'") && str_contains($sql, 'COUNT(DISTINCT BINARY') && str_contains($sql, "pm.meta_key = 'protocol'")), 12)->andReturn('prepared');
        $db->shouldReceive('get_results')->once()->with('prepared')->andReturn($rows);
        $GLOBALS['wpdb'] = $db;
        if ($error !== '') {
            Functions\expect('wp_cache_set')->never();
        } else {
            Functions\expect('wp_cache_set')->once()->with('post_12', \Mockery::type('array'), 'tacobout_engagement', 300)->andReturn(true);
        }
        return tacobout_get_engagement(12);
    }

    public function test_changed_generation_replaces_old_snapshot(): void {
        $cached = ['generation' => 'old', 'time' => time(), 'data' => tacobout_normalize_engagement([])];
        $data = $this->query_with_cache($cached, [(object) ['comment_type' => 'like', 'protocol' => 'atproto', 'total' => 7]]);
        $this->assertSame(7, $data['total']);
        $this->assertFalse($data['is_stale']);
    }

    public function test_expired_snapshot_recomputes_even_without_persistent_cache_expiration(): void {
        $cached = ['generation' => 'new', 'time' => time() - 301, 'data' => tacobout_normalize_engagement([])];
        $data = $this->query_with_cache($cached, [(object) ['comment_type' => 'comment', 'protocol' => '', 'total' => 1]]);
        $this->assertSame(1, $data['total']);
    }

    public function test_database_failure_preserves_old_data_and_marks_stale(): void {
        $data = tacobout_normalize_engagement([(object) ['comment_type' => 'like', 'protocol' => 'activitypub', 'total' => 9]]);
        $cached = ['generation' => 'old', 'time' => time(), 'data' => $data];
        $result = $this->query_with_cache($cached, null, 'database unavailable');
        $this->assertSame(9, $result['total']);
        $this->assertTrue($result['is_stale']);
        $this->assertSame('local_query_failed', $result['source']);
    }

    public function test_database_failure_without_snapshot_is_marked_partial_zero(): void {
        $data = $this->query_with_cache(false, null, 'database unavailable');
        $this->assertSame(0, $data['total']);
        $this->assertTrue($data['is_stale']);
        $this->assertTrue($data['is_partial']);
    }

    public function test_deleted_comment_uses_supplied_object(): void {
        Functions\expect('get_comment')->never();
        Functions\expect('wp_cache_delete')->once()->with('post_12', 'tacobout_engagement');
        tacobout_clear_interaction_count_cache(99, (object) ['comment_post_ID' => 12]);
        $this->assertTrue(true);
    }

    public function test_protocol_metadata_and_sync_completion_invalidate_same_key(): void {
        Functions\expect('get_comment')->times(3)->with(99)->andReturn((object) ['comment_post_ID' => 12]);
        Functions\expect('wp_cache_delete')->times(3)->with('post_12', 'tacobout_engagement');
        tacobout_engagement_comment_meta_changed(1, 99, 'protocol');
        tacobout_engagement_comment_meta_changed(1, 99, 'source_id');
        tacobout_clear_interaction_count_cache(99);
        tacobout_engagement_comment_meta_changed(1, 99, 'avatar');
        $this->assertTrue(true);
    }

    public function test_rest_fields_share_normalized_total(): void {
        $fields = [];
        Functions\expect('register_rest_field')->times(3)->andReturnUsing(function ($type, $name, $args) use (&$fields) {
            $fields[$name] = $args;
        });
        tacobout_register_rest_fields();
        $data = tacobout_normalize_engagement([(object) ['comment_type' => 'like', 'protocol' => 'atproto', 'total' => 3]]);
        Functions\expect('wp_cache_get_last_changed')->twice()->andReturn('new');
        Functions\expect('wp_cache_get')->twice()->andReturn(['generation' => 'new', 'time' => time(), 'data' => $data]);
        $this->assertSame(3, $fields['interaction_count']['get_callback'](['id' => 12]));
        $this->assertSame($data, $fields['engagement']['get_callback'](['id' => 12]));
        $this->assertSame('integer', $fields['interaction_count']['schema']['type']);
        $this->assertSame('object', $fields['engagement']['schema']['type']);
    }

    public function test_rest_caching_is_limited_to_public_post_reads(): void {
        foreach ([['/wp/v2/posts', '', 'view', 200, true], ['/wp/v2/posts/12', '', 'view', 200, true],
            ['/enable-mastodon-apps/api/v1/notifications', '', 'view', 200, false],
            ['/wp/v2/posts/12/revisions', '', 'view', 200, false],
            ['/wp/v2/posts', 'Bearer private', 'view', 200, false],
            ['/wp/v2/posts', '', 'edit', 200, false], ['/wp/v2/posts', '', 'view', 401, false]] as [$route, $auth, $context, $status, $cacheable]) {
            $request = \Mockery::mock();
            $request->shouldReceive('get_method')->andReturn('GET');
            $request->shouldReceive('get_route')->andReturn($route);
            $request->shouldReceive('get_header')->with('authorization')->andReturn($auth);
            $request->shouldReceive('get_header')->with('x-wp-nonce')->andReturn('');
            $request->shouldReceive('get_param')->with('context')->andReturn($context);
            $response = \Mockery::mock();
            $response->shouldReceive('get_status')->andReturn($status);
            if ($cacheable) {
                $response->shouldReceive('header')->once()->with('Cache-Control', 'public, max-age=60, s-maxage=120, must-revalidate');
            } else {
                $response->shouldNotReceive('header');
            }
            $this->assertSame($response, tacobout_rest_cache_control_headers($response, null, $request));
        }
    }
}
