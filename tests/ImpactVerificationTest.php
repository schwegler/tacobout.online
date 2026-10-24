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
        $this->expectOutputString("<meta name='impact-site-verification' value='33fbb505-59f2-47a6-bcbe-8478f65ca72b'>\n");
        tacobout_impact_site_verification();
    }

    public function test_other_pages_do_not_publish_the_proof() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(false);
        $this->expectOutputString('');
        tacobout_impact_site_verification();
    }

    public function test_paginated_front_page_does_not_publish_the_proof() {
        \Brain\Monkey\Functions\when('is_front_page')->justReturn(true);
        \Brain\Monkey\Functions\when('is_paged')->justReturn(true);
        $this->expectOutputString('');
        tacobout_impact_site_verification();
    }
}
