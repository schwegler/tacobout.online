<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class FunctionsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Define ABSPATH if not defined
        if (!defined('ABSPATH')) {
            define('ABSPATH', true);
        }

        // Mock WordPress functions called in functions.php
        Functions\when('add_filter')->justReturn();
        Functions\when('add_action')->justReturn();
        Functions\when('remove_action')->justReturn();
        Functions\when('add_theme_support')->justReturn();
        Functions\when('add_editor_style')->justReturn();
        Functions\when('load_theme_textdomain')->justReturn();
        Functions\when('is_admin')->justReturn(false);
        Functions\when('get_template_directory_uri')->justReturn('');
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('wp_enqueue_script')->justReturn();
        Functions\when('register_block_style')->justReturn();
        Functions\when('register_rest_field')->justReturn();

        // Load functions.php
        require_once __DIR__ . '/../functions.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_tacobout_pagination_body_class_no_pagination() {
        $initial_classes = ['home', 'blog'];
        $_GET = [];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog'], $result);
    }

    public function test_tacobout_pagination_body_class_traditional_pagination() {
        $initial_classes = ['home', 'blog'];
        $_GET = ['paged' => '2'];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog'], $result);
    }

    public function test_tacobout_pagination_body_class_query_block_pagination() {
        $initial_classes = ['home', 'blog'];
        $_GET = ['query-1-page' => '2'];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog', 'paged'], $result);
    }

    public function test_tacobout_pagination_body_class_query_block_pagination_no_id() {
        $initial_classes = ['home', 'blog'];
        $_GET = ['query-page' => '3'];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog', 'paged'], $result);
    }

    public function test_tacobout_pagination_body_class_first_page() {
        $initial_classes = ['home', 'blog'];
        $_GET = ['query-2-page' => '1'];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog'], $result);
    }

    public function test_tacobout_pagination_body_class_invalid_page() {
        $initial_classes = ['home', 'blog'];
        $_GET = ['query-page' => 'not-a-number'];
        $result = tacobout_pagination_body_class($initial_classes);
        $this->assertEquals(['home', 'blog'], $result);
    }
}
