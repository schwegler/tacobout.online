<?php
// PHPUnit Mock functions for WordPress

// Define ABSPATH if not defined
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// Global variable to store mocked return values
global $mock_post_formats;
$mock_post_formats = [];

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

// Override get_post_format for testing
if ( ! function_exists( 'get_post_format' ) ) {
    function get_post_format( $post = null ) {
        global $mock_post_formats;
        $post_id = is_object( $post ) ? $post->ID : (int) $post;
        return isset( $mock_post_formats[ $post_id ] ) ? $mock_post_formats[ $post_id ] : false;
    }
}

// Load other required mock functions for functions.php parsing
if ( ! function_exists( 'add_theme_support' ) ) {
    function add_theme_support( $feature, $args = null ) {}
}

if ( ! function_exists( 'add_editor_style' ) ) {
    function add_editor_style( $stylesheet = 'editor-style.css' ) {}
}

if ( ! function_exists( 'load_theme_textdomain' ) ) {
    function load_theme_textdomain( $domain, $path = false ) {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
}

if ( ! function_exists( 'get_stylesheet_uri' ) ) {
    function get_stylesheet_uri() { return ''; }
}

if ( ! function_exists( 'wp_get_theme' ) ) {
    class WP_Theme_Mock {
        public function get($key) { return '1.0'; }
    }
    function wp_get_theme() { return new WP_Theme_Mock(); }
}

if ( ! function_exists( 'wp_style_is' ) ) {
    function wp_style_is( $handle, $list = 'enqueued' ) { return true; }
}

if ( ! function_exists( 'wp_register_style' ) ) {
    function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
}

if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
    function get_template_directory_uri() { return ''; }
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
    function wp_add_inline_script( $handle, $data, $position = 'after' ) {}
}

if ( ! function_exists( 'register_block_style' ) ) {
    function register_block_style( $block_name, $style_properties ) {}
}
if ( ! function_exists( 'add_image_size' ) ) {
    function add_image_size( $name, $width = 0, $height = 0, $crop = false ) {}
}
if ( ! function_exists( 'has_post_format' ) ) {
    function has_post_format( $format = array(), $post = null ) { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
    function is_singular( $post_types = '' ) { return false; }
}
if ( ! function_exists( 'remove_action' ) ) {
    function remove_action( $hook_name, $callback, $priority = 10 ) { return true; }
}
if ( ! function_exists( 'remove_filter' ) ) {
    function remove_filter( $hook_name, $callback, $priority = 10 ) { return true; }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}

// Require the file to test
require_once dirname( __DIR__ ) . '/functions.php';
