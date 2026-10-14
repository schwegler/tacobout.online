<?php

class RedesignBrandTest extends \PHPUnit\Framework\TestCase {
    public function test_style_css_defines_brand_tokens_and_mascot_sticker() {
        $css = file_get_contents(__DIR__ . '/../style.css');

        $this->assertStringContainsString('--taco-blue: #3B82F6;', $css);
        $this->assertStringContainsString('--trove-violet: #7C3AED;', $css);
        $this->assertStringContainsString('--shared-gold: #F59E0B;', $css);
        $this->assertStringContainsString('--shadow-comic: 4px 4px 0px #0F0E17;', $css);
        $this->assertStringContainsString('.mascot-sticker', $css);
        $this->assertStringContainsString('filter: drop-shadow(2px 3px 0px rgba(15, 14, 23, 0.22));', $css);
    }

    public function test_mascot_assets_exist_in_brand_directory() {
        $brand_dir = __DIR__ . '/../assets/brand/';
        $required_assets = [
            'telly_pose_01_on_the_mic.png',
            'telly_pose_02_taco_break.png',
            'telly_pose_03_side_eye.png',
            'telly_pose_04_breaking_hype.png',
            'tacobout_rounded_app_icon.png',
            'tacobout_favicon_32px.png',
            'mascot_duo_telly_and_facet_transparent.png',
            'facet_pose_04_five_star.png',
        ];

        foreach ($required_assets as $asset) {
            $this->assertFileExists($brand_dir . $asset, "Expected mascot asset {$asset} to exist");
        }
    }
}
