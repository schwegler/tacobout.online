<?php
class SidebarTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

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
}
