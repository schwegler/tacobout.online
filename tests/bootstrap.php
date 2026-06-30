<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Mock WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

// Mock WordPress functions
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        global $wp_styles_enqueued;
        if (!isset($wp_styles_enqueued)) {
            $wp_styles_enqueued = [];
        }
        $wp_styles_enqueued[$handle] = compact('src', 'deps', 'ver', 'media');
    }
}

if (!function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri() {
        return 'http://example.com/wp-content/themes/tacobout/style.css';
    }
}

class WP_Theme_Mock {
    public function get($header) {
        if ($header === 'Version') {
            return '1.0.0';
        }
        return '';
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return new WP_Theme_Mock();
    }
}

if (!function_exists('add_action')) {
    function add_action() {}
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script() {}
}

if (!function_exists('get_theme_file_uri')) {
    function get_theme_file_uri($file = '') {
        return 'http://example.com/wp-content/themes/tacobout/' . ltrim($file, '/');
    }
}

if (!function_exists('wp_style_add_data')) {
    function wp_style_add_data() {}
}

if (!function_exists('add_filter')) {
    function add_filter() {}
}

if (!function_exists('add_theme_support')) {
    function add_theme_support() {}
}

if (!function_exists('register_nav_menus')) {
    function register_nav_menus() {}
}
if (!function_exists('__')) {
    function __($text) { return $text; }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain) { return $text; }
}

// Load functions.php

if (!function_exists('remove_action')) {
    function remove_action() {}
}
require_once __DIR__ . '/../functions.php';
