<?php

class FunctionsTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        global $wp_styles_enqueued;
        $wp_styles_enqueued = [];
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_tacobout_enqueue_styles() {
        global $wp_styles_enqueued;

        // Ensure array starts empty
        $this->assertEmpty($wp_styles_enqueued);

        // Call the function
        tacobout_enqueue_styles();

        // Check if styles were enqueued
        $this->assertArrayHasKey('tacobout-style', $wp_styles_enqueued);
        $this->assertEquals('http://example.com/wp-content/themes/tacobout/style.css', $wp_styles_enqueued['tacobout-style']['src']);
        $this->assertEquals('1.0.0', $wp_styles_enqueued['tacobout-style']['ver']);
        $this->assertEquals([], $wp_styles_enqueued['tacobout-style']['deps']);

        $this->assertArrayHasKey('tacobout-google-fonts', $wp_styles_enqueued);
        $this->assertEquals('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap', $wp_styles_enqueued['tacobout-google-fonts']['src']);
        $this->assertNull($wp_styles_enqueued['tacobout-google-fonts']['ver']);
        $this->assertEquals([], $wp_styles_enqueued['tacobout-google-fonts']['deps']);
    }

    public function test_tacobout_clear_saved_templates_already_cleared() {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with('tacobout_templates_cleared_v2')
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('get_posts')->never();
        \Brain\Monkey\Functions\expect('wp_delete_post')->never();
        \Brain\Monkey\Functions\expect('update_option')->never();
        \Brain\Monkey\Functions\expect('get_stylesheet')->never();

        tacobout_clear_saved_templates();

        // BrainMonkey expect functions handle assertions under the hood, but PHPUnit doesn't know this.
        // We can add a dummy assertion to prevent the risky test warning, or we can configure PHPUnit to ignore it.
        $this->assertTrue(true);
    }

    public function test_tacobout_clear_saved_templates_clears_templates() {
        \Brain\Monkey\Functions\expect('get_option')
            ->once()
            ->with('tacobout_templates_cleared_v2')
            ->andReturn(false);

        \Brain\Monkey\Functions\expect('get_stylesheet')
            ->once()
            ->andReturn('tacobout');

        // We mock get_posts to return an array of mock posts
        $post1 = 10;
        $post2 = 20;
        $post3 = 30;

        // Mock get_posts to return different results based on the arguments
        \Brain\Monkey\Functions\expect('get_posts')
            ->twice()
            ->andReturnUsing(function($args) use ($post1, $post2, $post3) {
                if ($args['post_type'] === 'wp_template') {
                    return [$post1, $post2];
                } elseif ($args['post_type'] === 'wp_template_part') {
                    return [$post3];
                }
                return [];
            });

        // We expect wp_delete_post to be called with the ID of each post returned by get_posts
        \Brain\Monkey\Functions\expect('wp_delete_post')
            ->times(3)
            ->with(\Mockery::anyOf(10, 20, 30), true);

        // We expect update_option to be called at the end
        \Brain\Monkey\Functions\expect('update_option')
            ->once()
            ->with('tacobout_templates_cleared_v2', true);

        tacobout_clear_saved_templates();

        $this->assertTrue(true);
    }
}
