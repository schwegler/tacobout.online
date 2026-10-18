<?php
class ApprovedCreatorLogoTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    public function test_creator_credit_uses_approved_png_treatments_and_preserves_contact_encoding() {
        \Brain\Monkey\Functions\when('esc_url')->returnArg();
        \Brain\Monkey\Functions\when('antispambot')->justReturn('encoded-contact');
        $html = tacobout_creator_credit_shortcode();
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $credit = $xpath->query('//a[@class="creator-credit"]')->item(0);
        $this->assertSame('https://schweg.xyz/', $credit->getAttribute('href'));
        $this->assertSame('A schweg.xyz project', $credit->getAttribute('aria-label'));
        $images = $credit->getElementsByTagName('img');
        $this->assertSame(2, $images->length);
        foreach (['ink', 'light'] as $index => $treatment) {
            $this->assertStringEndsWith("assets/brand/schweg-signature-{$treatment}.png", $images->item($index)->getAttribute('src'));
            $this->assertSame('132', $images->item($index)->getAttribute('width'));
            $this->assertSame('39', $images->item($index)->getAttribute('height'));
        }
        $this->assertStringContainsString('mailto:encoded-contact', $html);
        $this->assertStringNotContainsString('schweg-xyz-ink.svg', $html);
    }
}
