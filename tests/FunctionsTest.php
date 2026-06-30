<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class FunctionsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Monkey\Functions\when('add_action')->justReturn();

        require_once dirname(__DIR__) . '/functions.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_tacobout_support() {
        Monkey\Functions\expect('add_editor_style')
            ->once()
            ->with('style.css');

        Monkey\Functions\expect('load_theme_textdomain')
            ->once()
            ->with('tacobout');

        Monkey\Functions\expect('add_theme_support')
            ->once()
            ->with('wp-block-styles');
        Monkey\Functions\expect('add_theme_support')
            ->once()
            ->with('responsive-embeds');
        Monkey\Functions\expect('add_theme_support')
            ->once()
            ->with('post-thumbnails');
        Monkey\Functions\expect('add_theme_support')
            ->once()
            ->with('post-formats', [
                'status',
                'image',
                'video',
                'quote',
                'link',
                'audio',
                'gallery',
            ]);

        tacobout_support();

        // Assert true to avoid risky test error since assertions are handled by Monkey
        $this->assertTrue(true);
    }
}
