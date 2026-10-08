<?php

class TroveTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_only_public_item_urls_are_accepted() {
        $this->assertSame('https://trove.schweg.xyz/books/13', tacobout_trove_item_url('https://trove.schweg.xyz/books/13/#review'));
        foreach ([
            'http://trove.schweg.xyz/books/13',
            'https://trove.schweg.xyz.evil.test/books/13',
            'https://trove.schweg.xyz@evil.test/books/13',
            'https://user:password@trove.schweg.xyz/books/13',
            'https://trove.schweg.xyz:443/books/13',
            'https://trove.schweg.xyz/books/13?token=private',
            'https://trove.schweg.xyz/users/1',
            'https://trove.schweg.xyz/login',
            'https://trove.schweg.xyz/books/../users/1',
            'https://trove.schweg.xyz/books/0',
            'javascript:alert(1)',
        ] as $url) {
            $this->assertSame('', tacobout_trove_item_url($url), $url);
        }
    }

    public function test_metadata_handles_attribute_order_entities_and_utf8() {
        $metadata = tacobout_trove_parse_metadata('<html><head><meta content="Björk &amp; friends | Trove" property="og:title"><meta property="og:description" content="Music • 2026"><meta property="og:image" content="https://example.test/art.jpg"></head></html>');
        $this->assertSame('Björk & friends | Trove', $metadata['og:title']);
        $this->assertSame('Music • 2026', $metadata['og:description']);
        $this->assertSame('https://example.test/art.jpg', $metadata['og:image']);
        $this->assertSame([], tacobout_trove_parse_metadata('<html><body>Sign in</body></html>'));
    }

    public function test_cached_metadata_does_not_fetch_again() {
        $cached = ['og:title' => 'A book | Trove'];
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn($cached);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->never();
        $this->assertSame($cached, tacobout_trove_metadata('https://trove.schweg.xyz/books/13'));
    }

    public function test_successful_metadata_is_cached_with_bounded_request() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->with('https://trove.schweg.xyz/books/13', [
            'timeout' => 3, 'redirection' => 0, 'limit_response_size' => 262144,
            'headers' => ['Accept' => 'text/html'],
        ])->andReturn(['ok' => true]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn('<meta property="og:title" content="A book | Trove">');
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_trove_' . md5('https://trove.schweg.xyz/books/13'), ['og:title' => 'A book | Trove'], 21600)->andReturn(true);
        $this->assertSame(['og:title' => 'A book | Trove'], tacobout_trove_metadata('https://trove.schweg.xyz/books/13'));
    }

    public function test_redirect_or_missing_item_uses_short_failure_cache() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn(['redirect' => true]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(302);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->never();
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_trove_' . md5('https://trove.schweg.xyz/books/13'), [], 300)->andReturn(true);
        $this->assertSame([], tacobout_trove_metadata('https://trove.schweg.xyz/books/13'));
    }

    public function test_network_failure_uses_short_failure_cache() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn(['error' => true]);
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->never();
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_trove_' . md5('https://trove.schweg.xyz/books/13'), [], 300)->andReturn(true);
        $this->assertSame([], tacobout_trove_metadata('https://trove.schweg.xyz/books/13'));
    }

    private function mock_rendering_functions() {
        \Brain\Monkey\Functions\when('shortcode_atts')->alias(function ($defaults, $attributes) { return array_merge($defaults, $attributes); });
        \Brain\Monkey\Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        \Brain\Monkey\Functions\when('esc_url')->alias(fn ($text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        \Brain\Monkey\Functions\when('esc_html__')->returnArg();
    }

    public function test_card_escapes_remote_text_and_rejects_unsafe_image() {
        $this->mock_rendering_functions();
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn([
            'og:title' => '<script>alert(1)</script> | Trove',
            'og:description' => '<img src=x onerror=alert(1)>',
            'og:image' => 'javascript:alert(1)',
        ]);
        $html = tacobout_trove_shortcode(['url' => 'https://trove.schweg.xyz/books/13', 'note' => '<b>My note</b>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;My note&lt;/b&gt;', $html);
        $this->assertStringNotContainsString(' | Trove', $html);
        $this->assertStringContainsString('href="https://trove.schweg.xyz/books/13"', $html);
    }

    public function test_missing_metadata_still_renders_a_working_link() {
        $this->mock_rendering_functions();
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn([]);
        $html = tacobout_trove_shortcode(['url' => 'https://trove.schweg.xyz/books/13', 'title' => 'My favourite book']);
        $this->assertStringContainsString('My favourite book', $html);
        $this->assertStringContainsString('View on Trove', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_invalid_url_does_not_make_a_request_or_render_a_card() {
        $this->mock_rendering_functions();
        \Brain\Monkey\Functions\expect('get_transient')->never();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->never();
        $this->assertSame('', tacobout_trove_shortcode(['url' => 'https://example.test/books/13']));
    }
}
