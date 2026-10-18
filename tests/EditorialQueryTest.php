<?php
class EditorialQueryTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    public function test_theme_search_query_uses_main_context_even_with_a_saved_override() {
        \Brain\Monkey\Functions\when('is_archive')->justReturn(false);
        \Brain\Monkey\Functions\when('is_search')->justReturn(true);
        $block = ['blockName'=>'core/query','attrs'=>['query'=>['inherit'=>false]],'innerBlocks'=>[
            ['blockName'=>'core/post-template','attrs'=>['className'=>'tacobout-magazine-grid']]
        ]];
        $this->assertTrue(tacobout_inherit_archive_query($block)['attrs']['query']['inherit']);
    }

    public function test_unrelated_custom_queries_and_homepage_choices_are_preserved() {
        \Brain\Monkey\Functions\when('is_archive')->justReturn(true);
        $block = ['blockName'=>'core/query','attrs'=>['query'=>['inherit'=>false]],'innerBlocks'=>[]];
        $this->assertSame($block, tacobout_inherit_archive_query($block));
        \Brain\Monkey\Functions\when('is_archive')->justReturn(false);
        \Brain\Monkey\Functions\when('is_search')->justReturn(false);
        $block['attrs']['namespace'] = 'tacobout/feed';
        $this->assertSame($block, tacobout_inherit_archive_query($block));
    }
}
