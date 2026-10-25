<?php

class ImpactVerificationTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_homepage_preserves_exact_impact_supplied_value_attribute() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(true);
        \Brain\Monkey\Functions\when('is_paged')->justReturn(false);
        $this->expectOutputString("<meta name='impact-site-verification' value='7859418e-1949-467e-b371-9f4133b5019f'>\n");
        tacobout_impact_site_verification();
    }

    public function test_other_pages_do_not_publish_the_proof() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(false);
        $this->expectOutputString('');
        tacobout_impact_site_verification();
    }

    public function test_homepage_head_has_exactly_one_current_verification_tag() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(true);
        \Brain\Monkey\Functions\when('is_paged')->justReturn(false);
        ob_start();
        tacobout_impact_site_verification();
        $output = ob_get_clean();
        $document = new \DOMDocument();
        $document->loadHTML('<!doctype html><html><head>' . $output . '</head><body></body></html>');
        $xpath = new \DOMXPath($document);
        $tags = $xpath->query('//meta[@name="impact-site-verification"]');
        $this->assertCount(1, $tags);
        $this->assertSame('head', $tags->item(0)->parentNode->nodeName);
        $this->assertSame('7859418e-1949-467e-b371-9f4133b5019f', $tags->item(0)->getAttribute('value'));
        $this->assertFalse($tags->item(0)->hasAttribute('content'));
        $this->assertSame(0, $xpath->query('//script')->length);
        // Registration is unchanged and occurs only once on the head hook.
        $this->assertSame(1, substr_count(file_get_contents(__DIR__ . '/../functions.php'), "add_action( 'wp_head', 'tacobout_impact_site_verification' );"));
    }

    public function test_paginated_front_page_does_not_publish_the_proof() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(true);
        \Brain\Monkey\Functions\when('is_paged')->justReturn(true);
        $this->expectOutputString('');
        tacobout_impact_site_verification();
    }
}
