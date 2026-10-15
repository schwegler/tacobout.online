<?php

class SingleArticleAndSidebarTest extends \PHPUnit\Framework\TestCase {
    public function test_single_template_calls_post_discussion_pattern() {
        $single_html = file_get_contents(__DIR__ . '/../templates/single.html');

        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/post-discussion"} /-->', $single_html);
        $this->assertStringNotContainsString('tacobout-author-card', $single_html);
    }

    public function test_post_discussion_pattern_contains_telly_and_fediverse_ctas() {
        $pattern = file_get_contents(__DIR__ . '/../patterns/post-discussion.php');

        $this->assertStringContainsString('telly_pose_02_taco_break.png', $pattern);
        $this->assertStringContainsString('Written by <strong>@schwegler</strong>', $pattern);
        $this->assertStringContainsString('Fuelled by tacos, Steam Deck &amp; cats (Rose &amp; Violet)', $pattern);
        $this->assertStringContainsString('Reply directly from Bluesky or Mastodon', $pattern);
        $this->assertStringContainsString('Taco ‘bout this on Bluesky →', $pattern);
        $this->assertStringContainsString('https://bsky.app/profile/schwegler.lol', $pattern);
        $this->assertStringContainsString('https://tacobout.online/@schwegler', $pattern);
        // Custom SVG icons used instead of emojis
        $this->assertStringContainsString('tacobout-action-icon', $pattern);
        $this->assertStringNotContainsString('🦋', $pattern);
        $this->assertStringNotContainsString('🌮', $pattern);
    }

    public function test_sidebar_php_implements_redesigned_widgets_with_mascots() {
        $sidebar_php = file_get_contents(__DIR__ . '/../inc/sidebar.php');

        $this->assertStringContainsString('Hot on the Wire', $sidebar_php);
        $this->assertStringContainsString('Most read over the past 7 days', $sidebar_php);
        $this->assertStringContainsString('telly_pose_04_breaking_hype.png', $sidebar_php);

        $this->assertStringContainsString('Freshly Logged in Trove', $sidebar_php);
        $this->assertStringContainsString('Live from the public review vault', $sidebar_php);
        $this->assertStringContainsString('Explore the shelf', $sidebar_php);
        $this->assertStringContainsString('facet_pose_04_five_star.png', $sidebar_php);

        // Verify Trove mascot is directly in the heading div matching the tacobout widget
        $this->assertMatchesRegularExpression('/tacobout-sidebar-heading.*?Freshly Logged in Trove.*?tacobout-sidebar-mascot/', $sidebar_php);
        $this->assertStringContainsString('tacobout-sidebar-shelf-link', $sidebar_php);

        // Verify SVG icons are used without system emojis
        $this->assertStringContainsString('tacobout-sidebar-icon', $sidebar_php);
        $this->assertStringNotContainsString('⚡', $sidebar_php);
        $this->assertStringNotContainsString('💎', $sidebar_php);
    }

    public function test_style_and_sidebar_css_define_module_rules_and_version() {
        $css = file_get_contents(__DIR__ . '/../style.css');
        $sidebar_css = file_get_contents(__DIR__ . '/../sidebar.css');

        $this->assertMatchesRegularExpression('/Version:\s*3\.8\.\d+/', $css);
        $this->assertStringContainsString('.tacobout-discussion-card', $css);
        $this->assertStringContainsString('.tacobout-bluesky-cta', $css);
        $this->assertStringContainsString('.tacobout-mastodon-cta', $css);

        $this->assertStringContainsString('.tacobout-sidebar-mascot', $sidebar_css);
        $this->assertStringContainsString('height: 44px;', $sidebar_css);
        $this->assertStringContainsString('.tacobout-sidebar-shelf-link', $sidebar_css);
        $this->assertStringContainsString('.tacobout-sidebar-icon', $sidebar_css);
    }
}
