<?php
class PostAvatarScopeTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp(); \Brain\Monkey\setUp();
        \Brain\Monkey\Functions\when('is_author')->justReturn(false);
        \Brain\Monkey\Functions\when('get_option')->justReturn(true);
        foreach (['is_home','is_front_page','is_archive','is_search','is_single'] as $name) { \Brain\Monkey\Functions\when($name)->justReturn(false); }
    }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
    private function render($marker,$context=[]) {
        return tacobout_render_metadata_avatar('<div>native untouched avatar</div>', ['attrs'=>['className'=>$marker]], (object)['context'=>$context]);
    }
    public function test_unmarked_avatars_are_never_changed() {
        $this->assertSame('<div>native untouched avatar</div>',$this->render('profile-avatar'));
        $this->assertSame('<div>native untouched avatar</div>',$this->render('tacobout-card-author-avatar-other'));
    }
    public function test_profile_feeds_and_global_avatar_preference_are_respected() {
        \Brain\Monkey\Functions\when('is_archive')->justReturn(true);
        \Brain\Monkey\Functions\when('is_author')->justReturn(true);
        $this->assertSame('',$this->render('tacobout-card-author-avatar'));
        \Brain\Monkey\Functions\when('is_author')->justReturn(false);
        \Brain\Monkey\Functions\when('get_option')->justReturn(false);
        $this->assertSame('',$this->render('tacobout-card-author-avatar'));
        $this->assertSame('',$this->render('tacobout-byline-avatar'));
    }
    public function test_cards_and_byline_markers_do_not_leak_into_other_contexts() {
        \Brain\Monkey\Functions\when('is_single')->justReturn(true);
        $this->assertSame('',$this->render('tacobout-card-author-avatar'));
        \Brain\Monkey\Functions\when('is_single')->justReturn(false);
        \Brain\Monkey\Functions\when('is_search')->justReturn(true);
        $this->assertSame('',$this->render('tacobout-byline-avatar'));
    }
    public function test_missing_post_context_does_not_guess_the_global_author() {
        foreach (['is_home','is_front_page','is_archive','is_search'] as $name) {
            \Brain\Monkey\Functions\when($name)->justReturn(true);
            $this->assertSame('',$this->render('tacobout-card-author-avatar'));
        }
    }
    public function test_post_author_lookup_uses_each_block_context_not_queried_user() {
        \Brain\Monkey\Functions\when('is_home')->justReturn(true);
        \Brain\Monkey\Functions\expect('get_post_field')->once()->with('post_author',123)->andReturn(9);
        \Brain\Monkey\Functions\expect('get_the_author_meta')->once()->with('display_name',9)->andReturn('');
        $this->assertSame('',$this->render('tacobout-card-author-avatar',['postId'=>123]));
    }
}
