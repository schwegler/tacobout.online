<?php
class SidebarTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    public function test_upgrade_replaces_stored_eight_hour_event() {
        \Brain\Monkey\Functions\expect('wp_get_scheduled_event')->once()->with('tacobout_refresh_public_reviews')->andReturn((object)['schedule'=>'tacobout_eight_hours', 'interval'=>28800]);
        \Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')->once()->with('tacobout_refresh_public_reviews');
        \Brain\Monkey\Functions\expect('wp_schedule_event')->once()->with(\Mockery::type('int'), 'tacobout_hourly', 'tacobout_refresh_public_reviews');
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->never();
        $this->assertNull(tacobout_schedule_review_refresh());
    }

    public function test_correct_schedule_is_not_reset_on_each_request() {
        \Brain\Monkey\Functions\expect('wp_get_scheduled_event')->once()->andReturn((object)['schedule'=>'tacobout_hourly', 'interval'=>3600]);
        \Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_event')->never();
        $this->assertNull(tacobout_schedule_review_refresh());
    }

    public function test_first_install_schedules_hourly_refresh() {
        \Brain\Monkey\Functions\expect('wp_get_scheduled_event')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_event')->once()->with(\Mockery::type('int'), 'tacobout_hourly', 'tacobout_refresh_public_reviews');
        $schedules = tacobout_review_cron_schedules(['hourly'=>['interval'=>3600]]);
        $this->assertSame(3600, $schedules['tacobout_hourly']['interval']);
        $this->assertArrayHasKey('hourly', $schedules);
        $this->assertArrayNotHasKey('tacobout_quarter_hour', $schedules);
        $this->assertNull(tacobout_schedule_review_refresh());
    }

    public function test_public_reviews_ignore_non_reviews_and_untrusted_links() {
        $card = '<div class="activity-card"><span class="activity-text">Ada reviewed a book</span><a class="activity-item-link" href="%s">Book</a>%s</div>';
        $quote = '<div class="activity-review-quote">Good &amp; thoughtful</div>';
        $html = '<div id="activity_feed">' . sprintf($card, '/books/13', $quote) . sprintf($card, '/books/14', '') . sprintf($card, 'https://evil.test/books/1', $quote) . '</div>';
        $this->assertSame([['url'=>'https://trove.schweg.xyz/books/13','title'=>'Ada reviewed a book','quote'=>'Good & thoughtful','item_title'=>'Book','author'=>'','time'=>'','image'=>'','rating'=>'']], tacobout_parse_public_reviews($html));
    }

    public function test_recent_views_rank_public_posts_and_filter_private_content() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('stats_get_csv')->once()->with('postviews', ['days'=>7, 'limit'=>100])->andReturn([
            ['post_id'=>1,'views'=>2], ['post_id'=>2,'views'=>20], ['post_id'=>3,'views'=>50], ['post_id'=>4,'views'=>0], ['error'=>'unavailable']
        ]);
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_trending_week', \Mockery::type('array'), 1800);
        \Brain\Monkey\Functions\when('is_single')->justReturn(false);
        \Brain\Monkey\Functions\when('get_post')->alias(function ($id) {
            return (object)['ID'=>$id, 'post_type'=>'post', 'post_status'=>$id===3?'private':'publish', 'post_password'=>''];
        });
        $this->assertSame([2,1], array_column(tacobout_trending_posts(), 'ID'));
    }

    public function test_failed_stats_retry_without_inventing_a_ranking() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('stats_get_csv')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_trending_week', [], 300);
        $this->assertSame([], tacobout_trending_posts());
    }
    public function test_review_details_include_public_cover_reviewer_and_rating() {
        $html = '<div id="activity_feed"><div class="activity-card"><div class="activity-thumbnail"><img src="https://images.example.test/cover.jpg"></div><span class="activity-text"><a class="activity-user-link">Ada</a> reviewed <a class="activity-item-link" href="/books/13">A book</a> (Rating: 3.5 ★)</span><div class="activity-review-quote">A good read</div><span class="activity-time">2 hours ago</span></div></div>';
        $review = tacobout_parse_public_reviews($html)[0];
        $this->assertSame('A book', $review['item_title']);
        $this->assertSame('Ada', $review['author']);
        $this->assertSame('3.5', $review['rating']);
        $this->assertSame('https://images.example.test/cover.jpg', $review['image']);
        $this->assertSame('', tacobout_sidebar_image_url('javascript:alert(1)'));
        $this->assertSame('', tacobout_sidebar_image_url('https://secret@images.example.test/cover.jpg'));
    }
    public function test_snapshot_reads_never_wait_on_trove() {
        $reviews = [['title'=>'Cached review']];
        \Brain\Monkey\Functions\expect('get_option')->once()->with('tacobout_reviews_snapshot', [])->andReturn(['reviews'=>$reviews, 'fetched_at'=>1]);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->never();
        $this->assertSame($reviews, tacobout_public_reviews());
    }

    public function test_timeout_keeps_snapshot_and_schedules_one_wakeup_retry() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('set_transient')->once();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->with('https://trove.schweg.xyz/', \Mockery::on(fn($args)=>$args['timeout']===30))->andReturn(false);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(true);
        \Brain\Monkey\Functions\expect('update_option')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->once()->with(\Mockery::type('int'), 'tacobout_refresh_review_page', [1,[],1]);
        $this->assertNull(tacobout_refresh_public_reviews());
    }

    public function test_activity_without_reviews_continues_to_next_public_page() {
        \Brain\Monkey\Functions\expect('get_transient')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('set_transient')->once();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn('<a rel="next" href="/?page=2">Next</a>');
        \Brain\Monkey\Functions\expect('update_option')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->once()->with(\Mockery::type('int'), 'tacobout_refresh_review_page', [2,[],0]);
        $this->assertNull(tacobout_refresh_public_reviews());
    }

    public function test_empty_scan_never_erases_last_good_reviews() {
        \Brain\Monkey\Functions\expect('set_transient')->once();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn('<p>No reviews</p>');
        \Brain\Monkey\Functions\expect('update_option')->never();
        $this->assertNull(tacobout_refresh_public_reviews(5));
    }
    public function test_scan_continues_past_bulk_activity_on_page_five() {
        \Brain\Monkey\Functions\expect('set_transient')->once()->with('tacobout_reviews_refresh_lock', 1, 7200);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->with('https://trove.schweg.xyz/?page=5', \Mockery::type('array'))->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn('<a rel="next" href="/?page=6">Next</a>');
        \Brain\Monkey\Functions\expect('update_option')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->once()->with(\Mockery::type('int'), 'tacobout_refresh_review_page', [6,[],0]);
        $this->assertNull(tacobout_refresh_public_reviews(5));
    }

    public function test_local_cover_paths_resolve_to_trove_but_protocol_relative_urls_do_not() {
        $html = '<div id="activity_feed"><div class="activity-card"><div class="activity-thumbnail"><img src="%s"></div><span class="activity-text"><a class="activity-item-link" href="/movies/223-rushmore-1998">Rushmore</a></span><div class="activity-review-quote">Good</div></div></div>';
        $this->assertSame('https://trove.schweg.xyz/media/covers/movie/223', tacobout_parse_public_reviews(sprintf($html, '/media/covers/movie/223'))[0]['image']);
        $this->assertSame('', tacobout_parse_public_reviews(sprintf($html, '//evil.test/cover'))[0]['image']);
    }

    public function test_three_reviews_replace_snapshot_and_stop_pagination() {
        $card = '<div class="activity-card"><span class="activity-text"><a class="activity-item-link" href="/books/%d">Book</a></span><div class="activity-review-quote">Good</div></div>';
        $html = '<div id="activity_feed">' . sprintf($card, 1) . sprintf($card, 2) . sprintf($card, 3) . '</div><a rel="next">Next</a>';
        \Brain\Monkey\Functions\expect('set_transient')->once();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn($html);
        \Brain\Monkey\Functions\expect('update_option')->once()->with('tacobout_reviews_snapshot', \Mockery::on(fn($snapshot) => count($snapshot['reviews']) === 3 && $snapshot['reviews'][0]['url'] === 'https://trove.schweg.xyz/books/1' && $snapshot['fetched_at'] > 0), false);
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->never();
        $this->assertNull(tacobout_refresh_public_reviews(9));
    }

    public function test_scan_stops_at_safety_limit() {
        \Brain\Monkey\Functions\expect('set_transient')->once();
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn(200);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn('<a rel="next">Next</a>');
        \Brain\Monkey\Functions\expect('update_option')->never();
        \Brain\Monkey\Functions\expect('wp_schedule_single_event')->never();
        $this->assertNull(tacobout_refresh_public_reviews(200));
    }

}
