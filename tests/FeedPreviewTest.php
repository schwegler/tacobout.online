<?php
class FeedPreviewTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp(); \Brain\Monkey\setUp();
        \Brain\Monkey\Functions\when('home_url')->justReturn('https://example.com');
        \Brain\Monkey\Functions\when('esc_html')->alias('htmlspecialchars');
        \Brain\Monkey\Functions\when('esc_url')->returnArg();
        \Brain\Monkey\Functions\when('get_permalink')->justReturn('https://example.com/post/');
        \Brain\Monkey\Functions\when('get_the_title')->justReturn('The post');
        \Brain\Monkey\Functions\when('wp_trim_words')->alias(function ($s,$n,$more) { $words=preg_split('/\s+/',trim($s));return implode(' ',array_slice($words,0,$n)).(count($words)>$n?$more:''); });
    }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
    public function test_image_preview_keeps_primary_image_caption_and_direct_source_not_commentary() {
        $input='<div class="wp-block-post-content"><figure><img src="frog.jpg" width="1920" height="1551" alt="Frog"><figcaption>Colima vessel</figcaption></figure><p>Commentary <a href="https://www.metmuseum.org/art/1">Met source</a></p><figure><img src="duplicate.jpg"></figure><h2>Extras</h2></div>';
        $out=tacobout_feed_preview($input,21,'image');
        $this->assertSame(1,substr_count($out,'<img'));
        $this->assertStringContainsString('width="1920" height="1551"',$out);
        $this->assertStringContainsString('<figcaption>Colima vessel</figcaption>',$out);
        $this->assertStringContainsString('href="https://www.metmuseum.org/art/1"',$out);
        $this->assertStringContainsString('Open source',$out);
        $this->assertStringContainsString('Read more',$out);
        $this->assertStringNotContainsString('Commentary',$out);
        $this->assertStringNotContainsString('duplicate.jpg',$out);
        $this->assertStringNotContainsString('Extras',$out);
    }
    public function test_link_preview_exposes_source_bounds_quote_and_drops_extras() {
        $input='<div class="wp-block-post-content"><blockquote><p>'.str_repeat('Quoted ',100).'</p></blockquote><p><a href="javascript:alert(1)">Bad</a><a href="https://publication.test/article">Source</a></p><p>My commentary after the jump</p></div>';
        $out=tacobout_feed_preview($input,22,'link');
        $this->assertStringContainsString('<blockquote>',$out);
        $this->assertStringContainsString('href="https://publication.test/article"',$out);
        $this->assertLessThanOrEqual(55,substr_count($out,'Quoted'));
        $this->assertStringNotContainsString('javascript:',$out);
        $this->assertStringNotContainsString('My commentary',$out);
        $this->assertSame(1,substr_count($out,'wp-block-post-excerpt__more-link'));
    }
    public function test_link_keeps_one_sanitized_native_image_above_more_only() {
        $figure='<figure class="wp-block-image alignright is-resized"><a href="https://publication.test/article"><img src="body.jpg" width="160" height="120" alt="Different body image" srcset="body.jpg 160w, body-large.jpg 640w" sizes="160px"></a><figcaption>Authored caption</figcaption></figure>';
        \Brain\Monkey\Functions\when('wp_kses_allowed_html')->justReturn(['img'=>[]]);
        \Brain\Monkey\Functions\expect('wp_kses')->once()->with($figure, ['img'=>['sizes'=>true,'srcset'=>true]])->andReturn($figure);
        $input='<div class="wp-block-post-content"><blockquote><p>Source quote</p></blockquote>'.$figure.'<figure><img src="extra.jpg"></figure><span id="more-25"></span><p>Private commentary</p><img src="below.jpg"></div>';
        $out=tacobout_feed_preview($input,25,'link');
        $this->assertStringContainsString($figure,$out);
        $this->assertSame(1,substr_count($out,'<img'));
        $this->assertStringContainsString('Source quote',$out);
        $this->assertStringContainsString('Open source',$out);
        foreach (['extra.jpg','below.jpg','Private commentary'] as $hidden) $this->assertStringNotContainsString($hidden,$out);
    }
    public function test_link_does_not_promote_images_or_quotes_below_native_more_boundaries() {
        foreach (['<span id="more-26"></span>','<a class="more-link" href="/post#more-26">Continue</a>','<!--more-->'] as $marker) {
            $out=tacobout_feed_preview('<div class="wp-block-post-content"><p>Above jump</p>'.$marker.'<img src="below.jpg"><blockquote>Below jump</blockquote></div>',26,'link');
            $this->assertStringNotContainsString('below.jpg',$out);
            $this->assertStringNotContainsString('Below jump',$out);
            $this->assertStringContainsString('Above jump',$out);
        }
    }
    public function test_link_rest_preview_stops_before_more_without_mutating_full_article() {
        \Brain\Monkey\Functions\when('get_post_format')->justReturn('link');
        \Brain\Monkey\Functions\when('wp_kses_allowed_html')->justReturn(['img'=>[]]);
        \Brain\Monkey\Functions\when('wp_kses')->returnArg();
        $article='<p>Source introduction</p><figure class="wp-block-image alignleft"><img src="body.jpg" alt="Body"><figcaption>Caption</figcaption></figure><span id="more-402"></span><p>Full commentary <a href="https://later.test/story">Later source</a></p><img src="below.jpg">';
        $response=(object)['data'=>['content'=>['rendered'=>$article]]];
        $request=new class { public function get_route(){ return '/wp/v2/posts'; } };
        $result=tacobout_feed_rest_preview($response,(object)['ID'=>402],$request);
        $this->assertStringContainsString('body.jpg',$result->data['tacobout_feed_content']);
        $this->assertStringContainsString('alignleft',$result->data['tacobout_feed_content']);
        $this->assertStringNotContainsString('below.jpg',$result->data['tacobout_feed_content']);
        $this->assertStringNotContainsString('Full commentary',$result->data['tacobout_feed_content']);
        $this->assertSame($article,$result->data['content']['rendered']);
        $this->assertStringContainsString('https://later.test/story',$result->data['tacobout_feed_content']);
    }
    public function test_responsive_candidates_reject_unsafe_protocols_before_kses() {
        \Brain\Monkey\Functions\when('wp_kses_allowed_html')->justReturn(['img'=>[]]);
        \Brain\Monkey\Functions\expect('wp_kses')->once()->with('<img src="body.jpg" >', ['img'=>['sizes'=>true,'srcset'=>true]])->andReturn('<img src="body.jpg">');
        $this->assertSame('<img src="body.jpg">',tacobout_link_preview_media('<img src="body.jpg" srcset="/body.jpg 160w, javascript:alert(1) 640w">'));
    }
    public function test_other_formats_and_single_article_remain_authored() {
        $html='<div class="wp-block-post-content"><p>Full essay</p></div>';
        foreach(['standard','status','audio','quote','gallery'] as $format) $this->assertSame($html,tacobout_feed_preview($html,23,$format));
        $this->assertSame($html,tacobout_feed_card_content($html,[],(object)['context'=>['postId'=>23]]));
    }
    public function test_saved_query_excerpt_gets_one_shared_jump_label() {
        $html='<div class="wp-block-post-excerpt"><p>Intro <a class="wp-block-post-excerpt__more-link" href="https://example.com/post/">Tune in &amp; read →</a></p></div>';
        $out=tacobout_feed_excerpt_jump($html,[],(object)['context'=>['queryId'=>1,'postId'=>24]]);
        $this->assertStringContainsString('Read more<span class="screen-reader-text">: The post</span>',$out);
        $this->assertStringNotContainsString('Tune in',$out);
        $this->assertSame($html,tacobout_feed_excerpt_jump($html,[],(object)['context'=>['postId'=>24]]));
    }
    public function test_rest_collection_reuses_rendered_media_without_changing_single_responses() {
        \Brain\Monkey\Functions\when('get_post_format')->justReturn('image');
        $response=(object)['data'=>['content'=>['rendered'=>'<figure><img src="frog.jpg" alt="Frog"></figure><p>Extra commentary</p>']]];
        $request=new class { public function get_route(){ return '/wp/v2/posts'; } };
        $result=tacobout_feed_rest_preview($response,(object)['ID'=>401],$request);
        $this->assertStringContainsString('frog.jpg',$result->data['tacobout_feed_content']);
        $this->assertStringNotContainsString('Extra commentary',$result->data['tacobout_feed_content']);
        $this->assertStringContainsString('Extra commentary',$result->data['content']['rendered']);
        $single=new class { public function get_route(){ return '/wp/v2/posts/401'; } };
        $original=(object)['data'=>['content'=>['rendered'=>'Original article']]];
        $this->assertSame($original,tacobout_feed_rest_preview($original,(object)['ID'=>401],$single));
    }

}
