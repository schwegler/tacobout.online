<?php
namespace Activitypub\Collection {
    /** Only a test double for ActivityPub's public actor lookup. */
    class Actors {
        public static $actor;
        public static function get_by_id($id) { return self::$actor; }
    }
}
namespace {
    if (!class_exists('WP_User')) {
        class WP_User {
            public $ID = 7;
            public $user_nicename = 'facet';
            public $display_name = '<Facet>';
            public $description = 'An editorial mascot.';
        }
    }
    if (!class_exists('WP_Block_Type_Registry')) {
        class WP_Block_Type_Registry {
            public static function get_instance() { return new self(); }
            public function is_registered($name) { return true; }
        }
    }
    class AuthorProfileTest extends \PHPUnit\Framework\TestCase {
        protected function setUp(): void {
            parent::setUp(); \Brain\Monkey\setUp();
            \Activitypub\Collection\Actors::$actor = null;
            \Brain\Monkey\Functions\when('is_author')->justReturn(true);
            \Brain\Monkey\Functions\when('get_queried_object')->justReturn(new WP_User());
            \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
            \Brain\Monkey\Functions\when('esc_html__')->returnArg();
            \Brain\Monkey\Functions\when('esc_attr__')->returnArg();
            \Brain\Monkey\Functions\when('esc_html')->alias(fn($v)=>htmlspecialchars($v, ENT_QUOTES));
            \Brain\Monkey\Functions\when('esc_attr')->alias(fn($v)=>htmlspecialchars($v, ENT_QUOTES));
            \Brain\Monkey\Functions\when('esc_url')->returnArg();
        }
        protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
        public function test_other_contexts_do_not_leak_an_author_or_generate_controls() {
            \Brain\Monkey\Functions\when('is_author')->justReturn(false);
            $this->assertSame('', tacobout_render_author_banner());
            $this->assertSame('', tacobout_render_author_profile());
            \Brain\Monkey\Functions\when('is_author')->justReturn(true);
            \Brain\Monkey\Functions\when('get_queried_object')->justReturn((object)['ID'=>7]);
            $this->assertNull(tacobout_profile_author());
        }
        public function test_configured_plugin_banner_wins_over_default_art() {
            \Activitypub\Collection\Actors::$actor = new class {
                public function get_image() { return ['url'=>'https://example.test/custom-banner.png']; }
            };
            $html = tacobout_render_author_banner();
            $this->assertStringContainsString('https://example.test/custom-banner.png', $html);
            $this->assertStringNotContainsString('author-facet.png', $html);
            $this->assertStringContainsString('alt=""', $html);
        }
        public function test_unknown_users_do_not_receive_another_users_character() {
            $user = new WP_User(); $user->user_nicename = 'guest-editor';
            \Brain\Monkey\Functions\when('get_queried_object')->justReturn($user);
            $html = tacobout_render_author_banner();
            $this->assertStringContainsString('Plenty to talk about.', $html);
            $this->assertStringNotContainsString('<img', $html);
        }
        public function test_disabled_federation_falls_back_to_safe_native_profile() {
            \Brain\Monkey\Functions\when('get_avatar')->justReturn('<img alt="avatar">');
            \Brain\Monkey\Functions\when('wpautop')->returnArg();
            \Brain\Monkey\Functions\expect('wp_kses_post')->once()->with('An editorial mascot.')->andReturn('An editorial mascot.');
            $html = tacobout_render_author_profile();
            $this->assertStringContainsString('&lt;Facet&gt;', $html);
            $this->assertStringContainsString('An editorial mascot.', $html);
            $this->assertStringNotContainsString('Follow', $html);
        }
        public function test_native_block_owns_follow_and_copy_handle_is_escaped() {
            \Activitypub\Collection\Actors::$actor = new class {
                public function get_webfinger() { return 'facet@example.test" autofocus'; }
            };
            \Brain\Monkey\Functions\expect('do_blocks')->once()->with(\Mockery::on(function($content) {
                return str_contains($content, 'wp:activitypub/follow-me')
                    && str_contains($content, '"selectedUser":"inherit"')
                    && str_contains($content, 'data-profile-handle="@facet@example.test&quot; autofocus"');
            }))->andReturn('<div>native plugin profile and modal</div>');
            $this->assertSame('<div>native plugin profile and modal</div>', tacobout_render_author_profile());
        }
    }
}
