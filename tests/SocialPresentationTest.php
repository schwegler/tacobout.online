<?php
class SocialPresentationTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    public function test_record_links_require_a_complete_verified_post_uri() {
        $this->assertSame('https://bsky.app/profile/did%3Aplc%3Atest/post/3abc', tacobout_atproto_record_url('at://did:plc:test/app.bsky.feed.post/3abc'));
        foreach (['', false, [], 'javascript:alert(1)', 'at://did:plc:test/site.standard.document/3abc', 'at://did:plc:test/app.bsky.feed.post/3abc/extra', 'at://did:plc:test/app.bsky.feed.post/3abc?token=secret'] as $uri) {
            $this->assertSame('', tacobout_atproto_record_url($uri));
        }
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
