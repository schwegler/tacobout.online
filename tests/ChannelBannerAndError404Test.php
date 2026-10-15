<?php

class ChannelBannerAndError404Test extends \PHPUnit\Framework\TestCase {
    public function test_archive_template_calls_channel_banner_pattern() {
        $archive_html = file_get_contents(__DIR__ . '/../templates/archive.html');

        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/channel-banner"} /-->', $archive_html);
        $this->assertStringNotContainsString('wp:query-title', $archive_html);
    }

    public function test_channel_banner_pattern_contains_curated_channels_and_mascots() {
        $pattern = file_get_contents(__DIR__ . '/../patterns/channel-banner.php');

        $this->assertStringContainsString('TV, Free Streaming & Reality Mess', $pattern);
        $this->assertStringContainsString('Squared Circle & Storyline Deep Dives', $pattern);
        $this->assertStringContainsString('Live-Feed Receipt Book: Big Brother', $pattern);
        $this->assertStringContainsString('Food, Culinary Streams & Late-Night Snacks', $pattern);

        $this->assertStringContainsString('telly_pose_03_side_eye.png', $pattern);
        $this->assertStringContainsString('telly_pose_02_taco_break.png', $pattern);
        $this->assertStringContainsString('https://trove.schweg.xyz', $pattern);

        // Uses custom SVG icon without system emojis
        $this->assertStringContainsString('tacobout-badge-icon', $pattern);
        $this->assertStringNotContainsString('📺', $pattern);
        $this->assertStringNotContainsString('🤼', $pattern);
    }

    public function test_404_template_calls_error_pattern_with_dead_air_recovery() {
        $error_html = file_get_contents(__DIR__ . '/../templates/404.html');
        $error_pattern = file_get_contents(__DIR__ . '/../patterns/error-404.php');

        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/error-404"} /-->', $error_html);

        $this->assertStringContainsString('404: Dead Air on This Channel.', $error_pattern);
        $this->assertStringContainsString('Either that link moved, or you typed a URL from an alternate timeline.', $error_pattern);
        $this->assertStringContainsString('telly_pose_03_side_eye.png', $error_pattern);
        $this->assertStringContainsString('Back to Latest Broadcast', $error_pattern);
        $this->assertStringContainsString('Browse Media Trove Vault', $error_pattern);
        $this->assertStringContainsString('wp:search', $error_pattern);

        // Ensure no emojis used
        $this->assertStringNotContainsString('⚡', $error_pattern);
        $this->assertStringNotContainsString('💎', $error_pattern);
    }

    public function test_style_css_defines_channel_banner_and_404_rules_and_version() {
        $css = file_get_contents(__DIR__ . '/../style.css');

        $this->assertMatchesRegularExpression('/Version:\s*3\.8\.\d+/', $css);
        $this->assertStringContainsString('.tacobout-channel-banner', $css);
        $this->assertStringContainsString('.tacobout-channel-mascot', $css);
        $this->assertStringContainsString('.tacobout-error-card', $css);
        $this->assertStringContainsString('.tacobout-error-mascot', $css);
        $this->assertStringContainsString('.tacobout-error-primary-btn', $css);
    }
}
