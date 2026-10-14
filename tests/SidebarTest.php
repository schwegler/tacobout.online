<?php
class SidebarTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }

    private function review(): array {
        return ['id'=>'library-item-9', 'body'=>'Good <script>text</script> & thoughtful', 'item_title'=>'A book', 'item_url'=>'https://trove.schweg.xyz/books/9-a-book', 'reviewer'=>['display_name'=>'Ada'], 'rating'=>0, 'rating_scale'=>['min'=>0, 'max'=>5], 'cover_url'=>null];
    }

    private function response(int $status, string $body = '', array $snapshot = []): void {
        \Brain\Monkey\Functions\expect('get_option')->once()->with('tacobout_reviews_api_snapshot', [])->andReturn($snapshot);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->with('https://trove.schweg.xyz/api/reviews?limit=3', \Mockery::on(fn($args) => $args['redirection'] === 0 && $args['timeout'] === 5 && $args['headers']['Accept'] === 'application/json' && ($args['headers']['If-None-Match'] ?? null) === ($snapshot['etag'] ?? null)))->andReturn([]);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_response_code')->once()->andReturn($status);
        if ($status === 200) {
            \Brain\Monkey\Functions\expect('wp_remote_retrieve_body')->once()->andReturn($body);
        }
    }

    public function test_json_maps_zero_rating_and_null_cover() {
        $reviews = tacobout_parse_review_response(json_encode(['reviews'=>[$this->review()]]));
        $this->assertSame('0', $reviews[0]['rating']);
        $this->assertSame('', $reviews[0]['image']);
        $this->assertSame('Ada', $reviews[0]['author']);
        $this->assertSame('library-item-9', $reviews[0]['id']);
        $this->assertSame([], tacobout_parse_review_response('{"reviews":[]}'));
    }

    public function test_invalid_payloads_cannot_become_a_snapshot() {
        foreach (['not json', '{}', '{"reviews":null}', '{"reviews":{}}', '{"reviews":[{}]}'] as $body) {
            $this->assertNull(tacobout_parse_review_response($body));
        }
        $review = $this->review();
        $review['item_url'] = 'https://evil.test/books/9';
        $this->assertNull(tacobout_parse_review_response(json_encode(['reviews'=>[$review]])));
    }

    public function test_200_replaces_snapshot_and_etag() {
        $body = json_encode(['reviews'=>[$this->review()]]);
        $this->response(200, $body, ['etag'=>'"old"', 'reviews'=>[['quote'=>'old']]]);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_header')->once()->with([], 'etag')->andReturn('W/"new"');
        \Brain\Monkey\Functions\expect('update_option')->once()->with('tacobout_reviews_api_snapshot', ['reviews'=>tacobout_parse_review_response($body), 'etag'=>'W/"new"'], false);
        $this->assertSame('library-item-9', tacobout_public_reviews()[0]['id']);
    }

    public function test_304_reuses_only_the_conditionally_validated_snapshot() {
        $reviews = [['quote'=>'validated']];
        $this->response(304, '', ['etag'=>'W/"same"', 'reviews'=>$reviews]);
        \Brain\Monkey\Functions\expect('update_option')->never();
        $this->assertSame($reviews, tacobout_public_reviews());
    }

    public function test_unconditional_304_does_not_reuse_a_body() {
        $this->response(304);
        $this->assertSame([], tacobout_public_reviews());
    }

    public function test_empty_200_clears_previously_public_reviews() {
        $this->response(200, '{"reviews":[]}', ['etag'=>'"old"', 'reviews'=>[['quote'=>'now private']]]);
        \Brain\Monkey\Functions\expect('wp_remote_retrieve_header')->once()->andReturn('"empty"');
        \Brain\Monkey\Functions\expect('update_option')->once()->with('tacobout_reviews_api_snapshot', ['reviews'=>[], 'etag'=>'"empty"'], false);
        $this->assertSame([], tacobout_public_reviews());
    }

    public function test_http_failure_hides_previously_public_reviews() {
        $this->response(503, '', ['etag'=>'"old"', 'reviews'=>[['quote'=>'previously public']]]);
        \Brain\Monkey\Functions\expect('update_option')->never();
        $this->assertSame([], tacobout_public_reviews());
    }

    public function test_malformed_200_hides_previously_public_reviews() {
        $this->response(200, '<html>Error</html>', ['etag'=>'"old"', 'reviews'=>[['quote'=>'previously public']]]);
        \Brain\Monkey\Functions\expect('update_option')->never();
        $this->assertSame([], tacobout_public_reviews());
    }

    public function test_timeout_hides_saved_body() {
        \Brain\Monkey\Functions\expect('get_option')->once()->andReturn(['etag'=>'"old"', 'reviews'=>[['quote'=>'saved']]]);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->once()->andReturn(false);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(true);
        $this->assertSame([], tacobout_public_reviews());
    }

    public function test_rendering_escapes_text_and_preserves_zero_rating() {
        \Brain\Monkey\Functions\when('esc_html')->alias(fn($text)=>htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        \Brain\Monkey\Functions\when('esc_attr')->alias(fn($text)=>htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        \Brain\Monkey\Functions\when('esc_url')->alias(fn($text)=>htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        \Brain\Monkey\Functions\when('esc_html__')->returnArg();
        \Brain\Monkey\Functions\when('wp_trim_words')->returnArg();
        $html = tacobout_review_section(tacobout_parse_review_response(json_encode(['reviews'=>[$this->review()]])));
        $this->assertStringContainsString('&lt;script&gt;text&lt;/script&gt;', $html);
        $this->assertStringContainsString('Rated 0 out of 5', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('', tacobout_review_section([]));
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

    public function test_rest_response_is_uncacheable_and_failure_contains_no_review_html() {
        $this->response(503);
        $response = \Mockery::mock('overload:WP_REST_Response');
        $response->shouldReceive('__construct')->once()->with(['html'=>''], 200, ['Cache-Control'=>'no-store, private', 'Vary'=>'Cookie']);
        $this->assertInstanceOf('WP_REST_Response', tacobout_review_rest_response());
    }

    public function test_page_markup_contains_only_review_placeholder() {
        \Brain\Monkey\Functions\when('esc_attr__')->returnArg();
        \Brain\Monkey\Functions\when('esc_html__')->returnArg();
        \Brain\Monkey\Functions\when('esc_html')->returnArg();
        \Brain\Monkey\Functions\when('esc_url')->returnArg();
        \Brain\Monkey\Functions\expect('rest_url')->once()->with('tacobout/v1/reviews')->andReturn('https://example.com/wp-json/tacobout/v1/reviews');
        \Brain\Monkey\Functions\expect('get_transient')->once()->with('tacobout_trending_week')->andReturn([]);
        \Brain\Monkey\Functions\when('is_single')->justReturn(false);
        \Brain\Monkey\Functions\expect('get_posts')->once()->andReturn([]);
        \Brain\Monkey\Functions\expect('wp_safe_remote_get')->never();
        \Brain\Monkey\Functions\expect('get_option')->never();
        $html = tacobout_discovery_sidebar();
        $this->assertStringContainsString('data-tacobout-reviews="https://example.com/wp-json/tacobout/v1/reviews" hidden', $html);
        $this->assertStringNotContainsString('tacobout-review-list', $html);
    }

}
