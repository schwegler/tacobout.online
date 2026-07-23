<?php
if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return new class {
            public function get($header) { return '1.0.0'; }
        };
    }
}
