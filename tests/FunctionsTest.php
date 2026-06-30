<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class FunctionsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions called globally in functions.php
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();
        Functions\when('add_theme_support')->justReturn();
        Functions\when('get_template_directory')->justReturn(__DIR__);
        Functions\when('remove_theme_support')->justReturn();
        Functions\when('add_image_size')->justReturn();
        Functions\when('register_nav_menus')->justReturn();
        Functions\when('esc_html__')->returnArg();
        Functions\when('__')->returnArg();
        Functions\when('wp_style_is')->justReturn(false);
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('add_editor_style')->justReturn();
        Functions\when('load_theme_textdomain')->justReturn();

        // Include functions.php if not already included
        if (!function_exists('tacobout_enqueue_styles')) {
            require_once dirname(__DIR__) . '/functions.php';
        }
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_tacobout_enqueue_styles() {
        // Setup mocks for tacobout_enqueue_styles
        Functions\when('get_stylesheet_uri')->justReturn('http://example.com/style.css');

        $theme_mock = Mockery::mock();
        $theme_mock->shouldReceive('get')->with('Version')->andReturn('1.0.0');
        Functions\when('wp_get_theme')->justReturn($theme_mock);

        // Expectations
        Functions\expect('wp_enqueue_style')
            ->once()
            ->with('tacobout-style', 'http://example.com/style.css', [], '1.0.0');

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with('tacobout-google-fonts', 'https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap', [], null);

        // Execute function
        tacobout_enqueue_styles();

        // Ensure assertions run to avoid risky test error
        $this->assertTrue(true);
    }
}
