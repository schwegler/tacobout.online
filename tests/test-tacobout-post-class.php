<?php

use PHPUnit\Framework\TestCase;

class Test_Tacobout_Post_Class extends TestCase {

    public function setUp(): void {
        parent::setUp();
        global $mock_post_formats;
        $mock_post_formats = [];
    }

    public function tearDown(): void {
        parent::tearDown();
        global $mock_post_formats;
        $mock_post_formats = [];
    }

    public function test_adds_standard_format_when_no_format_set() {
        global $mock_post_formats;
        $post_id = 1;
        $mock_post_formats[$post_id] = false; // No format set

        $classes = ['initial-class'];
        $result = tacobout_post_class( $classes, [], $post_id );

        $this->assertContains( 'tacobout-format-standard', $result );
        $this->assertContains( 'initial-class', $result );
        $this->assertCount( 2, $result );
    }

    public function test_adds_specific_format_class() {
        global $mock_post_formats;
        $post_id = 2;
        $format = 'video';
        $mock_post_formats[$post_id] = $format;

        $classes = ['initial-class'];
        $result = tacobout_post_class( $classes, [], $post_id );

        $this->assertContains( 'tacobout-format-video', $result );
        $this->assertNotContains( 'tacobout-format-standard', $result );
        $this->assertContains( 'initial-class', $result );
        $this->assertCount( 2, $result );
    }

    public function test_handles_empty_initial_classes() {
        global $mock_post_formats;
        $post_id = 3;
        $format = 'status';
        $mock_post_formats[$post_id] = $format;

        $classes = [];
        $result = tacobout_post_class( $classes, [], $post_id );

        $this->assertContains( 'tacobout-format-status', $result );
        $this->assertCount( 1, $result );
    }

    public function test_all_supported_formats() {
        global $mock_post_formats;

        $formats = [
            'status',
            'image',
            'video',
            'quote',
            'link',
            'audio',
            'gallery'
        ];

        foreach ( $formats as $index => $format ) {
            $post_id = $index + 10;
            $mock_post_formats[$post_id] = $format;

            $result = tacobout_post_class( [], [], $post_id );

            $this->assertContains( 'tacobout-format-' . $format, $result );
            $this->assertCount( 1, $result );
        }
    }
}
