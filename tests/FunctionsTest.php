<?php

class FunctionsTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        global $wp_styles_enqueued;
        $wp_styles_enqueued = [];
    }

    public function test_tacobout_enqueue_styles() {
        global $wp_styles_enqueued;

        // Ensure array starts empty
        $this->assertEmpty($wp_styles_enqueued);

        // Call the function
        tacobout_enqueue_styles();

        // Check if styles were enqueued
        $this->assertArrayHasKey('tacobout-style', $wp_styles_enqueued);
        $this->assertEquals('http://example.com/wp-content/themes/tacobout/style.css', $wp_styles_enqueued['tacobout-style']['src']);
        $this->assertEquals('1.0.0', $wp_styles_enqueued['tacobout-style']['ver']);
        $this->assertEquals([], $wp_styles_enqueued['tacobout-style']['deps']);

        $this->assertArrayHasKey('tacobout-google-fonts', $wp_styles_enqueued);
        $this->assertEquals('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap', $wp_styles_enqueued['tacobout-google-fonts']['src']);
        $this->assertNull($wp_styles_enqueued['tacobout-google-fonts']['ver']);
        $this->assertEquals([], $wp_styles_enqueued['tacobout-google-fonts']['deps']);
    }
}
