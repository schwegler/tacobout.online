<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class FunctionsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_tacobout_post_class_with_format() {
        Functions\expect('get_post_format')
            ->once()
            ->with(123)
            ->andReturn('video');

        $result = tacobout_post_class(['class1', 'class2'], ['extra'], 123);

        $this->assertContains('tacobout-format-video', $result);
        $this->assertContains('class1', $result);
        $this->assertContains('class2', $result);
    }

    public function test_tacobout_post_class_without_format() {
        Functions\expect('get_post_format')
            ->once()
            ->with(456)
            ->andReturn(false);

        $result = tacobout_post_class(['class1'], [], 456);

        $this->assertContains('tacobout-format-standard', $result);
        $this->assertContains('class1', $result);
    }
}
