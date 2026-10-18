<?php

class ReleaseQualityGatesTest extends \PHPUnit\Framework\TestCase {
    public function test_companion_vault_pattern_and_about_template() {
        $pattern_path = __DIR__ . '/../patterns/companion-vault.php';
        $this->assertFileExists($pattern_path);
        $pattern = file_get_contents($pattern_path);

        $this->assertStringContainsString('The collection continues on Trove.', $pattern);
        $this->assertStringContainsString('Track movies, shows, comics, books, albums, and games. Organize your collection, log progress, and share reviews on Trove.', $pattern);
        $this->assertStringContainsString('mascot_duo_telly_and_facet_transparent.png', $pattern);
        $this->assertStringContainsString('https://trove.schweg.xyz', $pattern);
        $this->assertStringContainsString('tacobout-badge-icon', $pattern);

        // Emoji hygiene
        $this->assertStringNotContainsString('💎', $pattern);
        $this->assertStringNotContainsString('⚡', $pattern);

        $about_template = file_get_contents(__DIR__ . '/../templates/page-about.html');
        $this->assertStringContainsString('<!-- wp:pattern {"slug":"tacobout/companion-vault"} /-->', $about_template);
    }

    public function test_style_css_version_and_companion_vault_rules() {
        $css = file_get_contents(__DIR__ . '/../style.css');

        $this->assertStringContainsString('Version: 3.8.17', $css);
        $this->assertStringContainsString('.tacobout-companion-vault-card', $css);
        $this->assertStringContainsString('.tacobout-vault-mascot', $css);
        $this->assertStringContainsString('.tacobout-vault-badge', $css);
        $this->assertStringContainsString('.tacobout-vault-headline', $css);
        $this->assertStringContainsString('.tacobout-vault-subcopy', $css);
        $this->assertStringContainsString('[data-theme="dark"] .tacobout-companion-vault-card', $css);
    }

    public function test_all_redesign_mascots_and_brand_assets_exist() {
        $assets = array(
            'telly_pose_01_on_the_mic.png',
            'telly_pose_02_taco_break.png',
            'telly_pose_03_side_eye.png',
            'telly_pose_04_breaking_hype.png',
            'facet_pose_01_bookworm.png',
            'facet_pose_02_gamer_mode.png',
            'facet_pose_03_crate_digger.png',
            'facet_pose_01_bookworm.png',
            'mascot_duo_telly_and_facet_transparent.png',
            'tacobout_rounded_app_icon.png',
            'trove_rounded_app_icon.png',
        );

        foreach ($assets as $asset) {
            $this->assertFileExists(__DIR__ . '/../assets/brand/' . $asset, "Missing brand asset: {$asset}");
        }
    }

    public function test_emoji_hygiene_across_patterns() {
        $patterns = array(
            'on-the-air.php',
            'post-discussion.php',
            'channel-banner.php',
            'error-404.php',
            'companion-vault.php',
        );

        $emojis = array('💎', '⚡', '📺', '🤼', '🦋', '🌮');

        foreach ($patterns as $pattern_file) {
            $path = __DIR__ . '/../patterns/' . $pattern_file;
            $this->assertFileExists($path);
            $content = file_get_contents($path);

            foreach ($emojis as $emoji) {
                $this->assertStringNotContainsString(
                    $emoji,
                    $content,
                    "Pattern {$pattern_file} contains forbidden emoji {$emoji}"
                );
            }
        }
    }
}
