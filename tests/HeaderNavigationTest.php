<?php

class HeaderNavigationTest extends \PHPUnit\Framework\TestCase {
    public function test_header_contains_sister_brand_pill_navigation() {
        $header_html = file_get_contents(__DIR__ . '/../parts/header.html');

        $this->assertStringContainsString('💎 Media Trove & Library ↗', $header_html);
        $this->assertStringContainsString('https://trove.schweg.xyz', $header_html);
        $this->assertStringContainsString('tacobout-sister-nav-pill', $header_html);
    }

    public function test_style_css_defines_sister_nav_pill_rules() {
        $css = file_get_contents(__DIR__ . '/../style.css');

        $this->assertStringContainsString('.tacobout-sister-nav-pill', $css);
        $this->assertStringContainsString('--trove-violet', $css);
        $this->assertStringContainsString('--shared-lavender', $css);
    }
}
