<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock WP core constant
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Minimal stubs to allow functions.php to be loaded
function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {}
function remove_action($hook_name, $callback, $priority = 10) {}
function remove_filter($hook_name, $callback, $priority = 10) {}
function add_theme_support($feature, ...$args) {}
function add_editor_style($stylesheet = 'editor-style.css') {}
function load_theme_textdomain($domain, $path = false) {}
function get_stylesheet_uri() { return ''; }
function wp_get_theme() { return new class { public function get($prop) { return '1.0'; } }; }
function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {}
function wp_style_is($handle, $list = 'enqueued') { return true; }
function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {}
function register_block_style($block_name, $style_properties) {}

// Load theme functions
require_once dirname(__DIR__) . '/functions.php';
