<?php

class AssetFingerprintTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        \Brain\Monkey\Functions\when('get_template_directory_uri')->justReturn('https://example.com/wp-content/themes/tacobout');
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_theme_assets_keep_fingerprints_after_query_removal() {
        $manifest = json_decode(file_get_contents(__DIR__ . '/../asset-manifest.json'), true);
        foreach ($manifest as $source => $target) {
            $url = 'https://example.com/wp-content/themes/tacobout/' . $source;
            $expected = 'https://example.com/wp-content/themes/tacobout/' . $target;
            $this->assertSame($expected, tacobout_fingerprint_asset_src($url . '?ver=3.8.23'));
            $this->assertSame($expected, tacobout_fingerprint_asset_src($url));
            $this->assertSame(dirname($source), dirname($target), 'Relative image paths must retain their directory.');
            $this->assertSame(file_get_contents(__DIR__ . '/../' . $source), file_get_contents(__DIR__ . '/../' . $target));
        }
    }

    public function test_unrelated_assets_and_child_theme_overrides_are_preserved() {
        foreach ([
            'https://example.com/wp-content/themes/child/style.css?ver=1',
            'https://example.com/wp-content/plugins/plugin/script.js?ver=1',
            'https://c0.wp.com/c/7.1.3/wp-admin/css/admin-menu.min.css',
            'https://example.com/wp-content/themes/tacobout/assets/brand/telly_pose_01_on_the_mic.png?ver=1',
            'https://example.com/wp-content/themes/tacobout/custom.css?ver=1',
        ] as $url) {
            $this->assertSame($url, tacobout_fingerprint_asset_src($url));
        }
    }

    public function test_fingerprinted_urls_are_idempotent() {
        $url = tacobout_fingerprint_asset_src('https://example.com/wp-content/themes/tacobout/style.css');
        $this->assertSame($url, tacobout_fingerprint_asset_src($url));
    }
}
