<?php
// Bootstrap for PHPUnit
// Include the main functions.php file for testing
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

// Mock WordPress functions that we might need
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter() {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}
if ( ! function_exists( 'add_theme_support' ) ) {
    function add_theme_support() {}
}
if ( ! function_exists( 'remove_action' ) ) {
    function remove_action() {}
}
if ( ! function_exists( 'get_stylesheet_uri' ) ) {
    function get_stylesheet_uri() { return ''; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style() {}
}
if ( ! function_exists( 'add_editor_style' ) ) {
    function add_editor_style() {}
}
if ( ! function_exists( 'load_theme_textdomain' ) ) {
    function load_theme_textdomain() {}
}

// Mock wp_get_theme
if ( ! function_exists( 'wp_get_theme' ) ) {
    class WP_Theme {
        public function get($prop) { return '1.0'; }
    }
    function wp_get_theme() {
        return new WP_Theme();
    }
}

// Load the file to test
require_once dirname( dirname( __FILE__ ) ) . '/functions.php';
