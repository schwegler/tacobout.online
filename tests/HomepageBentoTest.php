<?php

class HomepageBentoTest extends \PHPUnit\Framework\TestCase {
    public function test_home_and_index_templates_call_on_the_air_pattern() {
        $home_html = file_get_contents(__DIR__ . '/../templates/home.html');
        $index_html = file_get_contents(__DIR__ . '/../templates/index.html');

        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/on-the-air"} /-->', $home_html);
        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/on-the-air"} /-->', $index_html);
    }

    public function test_on_the_air_pattern_contains_telly_and_broadcast_copy() {
        $pattern = file_get_contents(__DIR__ . '/../patterns/on-the-air.php');

        $this->assertStringContainsString('telly_pose_01_on_the_mic.png', $pattern);
        $this->assertStringContainsString('Plenty to talk about.', $pattern);
        $this->assertStringContainsString('tacobout-welcome-title', $pattern);
        $this->assertStringContainsString('Commentary, discoveries, and a well-earned side-eye.', $pattern);
        // Ensure no emoji is used in the badge
        $this->assertStringNotContainsString('⚡', $pattern);
    }

    public function test_query_and_infinite_scroll_use_consistent_read_more_cta() {
        $query_pattern = file_get_contents(__DIR__ . '/../patterns/query.php');
        $scroll_js = file_get_contents(__DIR__ . '/../tacobout-infinite-scroll.js');

        $this->assertStringContainsString('Read more', $query_pattern);
        $this->assertStringContainsString('Read more', $scroll_js);
    }

    public function test_end_of_feed_notice_pairs_telly_taco_break_with_trove_link() {
        $scroll_js = file_get_contents(__DIR__ . '/../tacobout-infinite-scroll.js');
        $functions_php = file_get_contents(__DIR__ . '/../functions.php');

        $this->assertStringContainsString('You’re caught up.', $scroll_js);
        $this->assertStringContainsString('There’s more in the archive. The collection continues on Trove.', $scroll_js);
        $this->assertStringContainsString('telly_pose_02_taco_break.png', $scroll_js);
        $this->assertStringContainsString('telly_pose_02_taco_break.png', $functions_php);
        $this->assertStringContainsString('https://trove.schweg.xyz', $scroll_js);
    }

    public function test_footer_tagline_links_to_trove_sister_broadcast() {
        $footer_html = file_get_contents(__DIR__ . '/../parts/footer.html');

        $this->assertStringContainsString('Sister broadcast to <a href="https://trove.schweg.xyz"', $footer_html);
        $this->assertStringContainsString('Trove (trove.schweg.xyz)</a>', $footer_html);
    }

    public function test_style_css_defines_hero_and_end_feed_styles_and_version_bump() {
        $css = file_get_contents(__DIR__ . '/../style.css');

        $this->assertMatchesRegularExpression('/Version:\s*3\.8\.\d+/', $css);
        $this->assertStringContainsString('.tacobout-on-the-air-strip', $css);
        $this->assertStringContainsString('.tacobout-end-of-feed', $css);
        $this->assertStringContainsString('.tacobout-end-card', $css);
        $this->assertStringContainsString('LATEST DROP', $css);
    }
}
