<?php
if (!class_exists('WP_Post')) { class WP_Post { public $post_author = 7; } }
if (!class_exists('WP_Comment')) { class WP_Comment { public $user_id = 0; } }
class AvatarPrecedenceTest extends \PHPUnit\Framework\TestCase {
    private array $fallback = ['url'=>'https://gravatar.test/fallback','found_avatar'=>false,'size'=>80,'class'=>['avatar']];
    protected function setUp(): void {
        parent::setUp(); \Brain\Monkey\setUp();
        \Brain\Monkey\Functions\when('get_user_option')->justReturn('42');
        \Brain\Monkey\Functions\when('get_post')->justReturn((object)['post_type'=>'attachment','post_status'=>'inherit']);
        \Brain\Monkey\Functions\when('wp_attachment_is_image')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_get_attachment_url')->justReturn('https://example.test/icon.png');
        \Brain\Monkey\Functions\when('esc_url_raw')->alias(fn($u)=>in_array(parse_url($u,PHP_URL_SCHEME),['https','http'],true)?$u:'');
        \Brain\Monkey\Functions\when('is_email')->alias(fn($v)=>filter_var($v,FILTER_VALIDATE_EMAIL));
        \Brain\Monkey\Functions\when('get_user_by')->justReturn(false);
    }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
    public function test_explicit_icon_wins_without_losing_other_avatar_arguments() {
        $result=tacobout_explicit_icon_avatar_data($this->fallback,7);
        $this->assertSame('https://example.test/icon.png',$result['url']);
        $this->assertTrue($result['found_avatar']);
        $this->assertSame(80,$result['size']);
        $this->assertSame(['avatar'],$result['class']);
    }
    public function test_missing_malformed_and_deleted_icons_keep_the_exact_fallback() {
        foreach ([false,'',0,'0',-1,'42junk',42.5,[],true] as $meta) {
            \Brain\Monkey\Functions\when('get_user_option')->justReturn($meta);
            $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
        }
        \Brain\Monkey\Functions\when('get_user_option')->justReturn(42);
        \Brain\Monkey\Functions\when('get_post')->justReturn(null);
        $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
        foreach ([(object)['post_type'=>'post','post_status'=>'publish'],(object)['post_type'=>'attachment','post_status'=>'trash']] as $post) {
            \Brain\Monkey\Functions\when('get_post')->justReturn($post);
            $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
        }
    }
    public function test_nonimage_and_missing_or_unsafe_urls_keep_fallback() {
        \Brain\Monkey\Functions\when('wp_attachment_is_image')->justReturn(false);
        $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
        \Brain\Monkey\Functions\when('wp_attachment_is_image')->justReturn(true);
        foreach ([false,'','javascript:alert(1)','/icon.png','//example.test/icon.png'] as $url) {
            \Brain\Monkey\Functions\when('wp_get_attachment_url')->justReturn($url);
            $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
        }
    }
    public function test_force_default_still_means_default() {
        $args=$this->fallback+['force_default'=>true];
        $this->assertSame($args,tacobout_explicit_icon_avatar_data($args,7));
    }
    public function test_local_user_post_and_email_inputs_resolve_but_remote_comments_do_not() {
        $this->assertSame(7,tacobout_avatar_user_id('7'));
        $this->assertSame(7,tacobout_avatar_user_id(new WP_User()));
        $this->assertSame(7,tacobout_avatar_user_id(new WP_Post()));
        \Brain\Monkey\Functions\when('get_user_by')->justReturn((object)['ID'=>7]);
        $this->assertSame(7,tacobout_avatar_user_id('editor@example.test'));
        $this->assertSame(0,tacobout_avatar_user_id('abc@sha256.gravatar.com'));
        \Brain\Monkey\Functions\when('get_comment_type')->justReturn('comment');
        \Brain\Monkey\Functions\when('is_avatar_comment_type')->justReturn(true);
        $comment=new WP_Comment();
        $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,$comment));
        $comment->user_id=7;
        $this->assertSame(7,tacobout_avatar_user_id($comment));
        \Brain\Monkey\Functions\when('is_avatar_comment_type')->justReturn(false);
        $this->assertSame(0,tacobout_avatar_user_id($comment));
    }
    public function test_recursion_returns_original_data_then_releases_guard() {
        $calls=0;
        \Brain\Monkey\Functions\when('wp_get_attachment_url')->alias(function() use (&$calls) {
            ++$calls;
            $this->assertSame($this->fallback,tacobout_explicit_icon_avatar_data($this->fallback,7));
            return 'https://example.test/icon.png';
        });
        $this->assertSame('https://example.test/icon.png',tacobout_explicit_icon_avatar_data($this->fallback,7)['url']);
        $this->assertSame('https://example.test/icon.png',tacobout_explicit_icon_avatar_data($this->fallback,7)['url']);
        $this->assertSame(2,$calls);
    }
    public function test_exception_does_not_leave_the_recursion_guard_locked() {
        \Brain\Monkey\Functions\when('wp_get_attachment_url')->alias(function(){throw new \RuntimeException('upstream');});
        try { tacobout_explicit_activitypub_icon(7); $this->fail('Expected upstream exception'); }
        catch (\RuntimeException $e) { $this->assertSame('upstream',$e->getMessage()); }
        \Brain\Monkey\Functions\when('wp_get_attachment_url')->justReturn('https://example.test/icon.png');
        $this->assertSame('https://example.test/icon.png',tacobout_explicit_activitypub_icon(7));
    }

}
