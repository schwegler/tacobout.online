<?php
class SocialPresentationTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    public function test_record_links_require_a_complete_verified_post_uri() {
        $this->assertSame('https://bsky.app/profile/did:plc:test/post/3abc', tacobout_atproto_record_url('at://did:plc:test/app.bsky.feed.post/3abc'));
        foreach (['', false, [], 'javascript:alert(1)', 'at://did:plc:test/site.standard.document/3abc', 'at://did:plc:test/app.bsky.feed.post/3abc/extra', 'at://did:plc:test/app.bsky.feed.post/3abc?token=secret'] as $uri) {
            $this->assertSame('', tacobout_atproto_record_url($uri));
        }
    }

    public function test_reported_post_link_preserves_did_colons() {
        $this->assertSame(
            'https://bsky.app/profile/did:plc:mjtyvn3cgsnkzlpahpu5pksw/post/3my5xz56yyxtf',
            tacobout_atproto_record_url('at://did:plc:mjtyvn3cgsnkzlpahpu5pksw/app.bsky.feed.post/3my5xz56yyxtf')
        );
    }

    public function test_original_link_requires_a_live_record_for_this_article() {
        $uri = 'at://did:plc:test/app.bsky.feed.post/3abc';
        $permalink = 'https://example.com/article/';
        \Brain\Monkey\Functions\when('get_permalink')->justReturn($permalink);
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        foreach ([[], [['uri'=>$uri,'record'=>['embed'=>['external'=>['uri'=>'https://example.com/other/']]]]], [['uri'=>$uri,'record'=>['embed'=>['external'=>['uri'=>$permalink]]]]]] as $posts) {
            \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['posts'=>$posts]));
            $expected = isset($posts[0]['record']['embed']['external']['uri']) && $posts[0]['record']['embed']['external']['uri'] === $permalink ? tacobout_atproto_record_url($uri) : '';
            $this->assertSame($expected, tacobout_verified_bluesky_url(42, $uri));
        }
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);
        $this->assertSame('', tacobout_verified_bluesky_url(42, $uri));
    }

    public function test_cached_missing_record_does_not_render_a_link() {
        \Brain\Monkey\Functions\when('get_permalink')->justReturn('https://example.com/article/');
        \Brain\Monkey\Functions\when('get_transient')->justReturn('unavailable');
        \Brain\Monkey\Functions\expect('wp_remote_get')->never();
        $this->assertSame('', tacobout_verified_bluesky_url(42, 'at://did:plc:test/app.bsky.feed.post/3abc'));
    }

    public function test_comment_provenance_requires_approved_known_protocol_and_safe_source() {
        \Brain\Monkey\Functions\when('is_feed')->justReturn(false);
        \Brain\Monkey\Functions\when('get_comment')->justReturn((object)['comment_approved'=>'1']);
        \Brain\Monkey\Functions\when('get_comment_meta')->alias(fn($id,$key)=>$key==='protocol'?'atproto':'javascript:alert(1)');
        \Brain\Monkey\Functions\when('esc_html')->alias('htmlspecialchars');
        $html = tacobout_comment_protocol('<a>Reader</a>', 'Reader', 9);
        $this->assertStringContainsString('via AT Protocol', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        \Brain\Monkey\Functions\when('get_comment')->justReturn((object)['comment_approved'=>'0']);
        $this->assertSame('<a>Reader</a>', tacobout_comment_protocol('<a>Reader</a>', 'Reader', 9));
    }

    public function test_feed_author_links_are_not_decorated() {
        \Brain\Monkey\Functions\when('is_feed')->justReturn(true);
        \Brain\Monkey\Functions\expect('get_comment')->never();
        $this->assertSame('Reader', tacobout_comment_protocol('Reader','Reader',1));
    }
}
