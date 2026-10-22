<?php
/** Run only against a local WordPress install: wp eval-file tests/link-feed-integration.php. No content writes. */
$check = function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$authored = '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Source extract</p></blockquote><!-- /wp:quote -->'
	. '<!-- wp:image {"align":"left","width":"160px"} --><figure class="wp-block-image alignleft is-resized"><a href="https://source.test/story"><img src="https://media.test/body.jpg" alt="Distinct body image" width="160" height="120" srcset="https://media.test/body.jpg 160w, https://media.test/large.jpg 640w" sizes="160px" onerror="alert(1)"></a><figcaption class="wp-element-caption">Original caption</figcaption></figure><!-- /wp:image -->'
	. '<!-- wp:more --><!--more--><!-- /wp:more --><!-- wp:paragraph --><p>Commentary below More</p><!-- /wp:paragraph --><!-- wp:image --><figure class="wp-block-image"><img src="https://media.test/below.jpg" alt="Below"></figure><!-- /wp:image -->';
// REST renders core/more as a span, while the Query loop may already cut the article.
$rendered = do_blocks( $authored );
$out = tacobout_feed_preview( '<div class="wp-block-post-content">' . $rendered . '</div>', 0, 'link' );
foreach ( array( 'body.jpg', 'alignleft', 'Original caption', 'Distinct body image', 'srcset=', 'sizes=', 'https://source.test/story', 'Open source', 'Read more' ) as $expected ) {
	$check( str_contains( $out, $expected ), 'Missing native content: ' . $expected );
}
foreach ( array( 'onerror', 'Commentary below More', 'below.jpg' ) as $forbidden ) {
	$check( ! str_contains( $out, $forbidden ), 'Unsafe or below-jump content: ' . $forbidden );
}
$check( 1 === substr_count( $out, '<img' ), 'More than one image' );
$unsafe = '<div class="wp-block-post-content"><figure class="wp-block-image"><a href="javascript:alert(1)"><img src="javascript:alert(1)" onload="alert(1)" srcset="/safe.jpg 160w, javascript:alert(1) 640w" alt="Safe label"></a><figcaption>Caption<script>alert(1)</script></figcaption></figure><p>Intro</p></div>';
$safe = tacobout_feed_preview( $unsafe, 0, 'link' );
foreach ( array( 'javascript:', 'onload=', '<script' ) as $forbidden ) {
	$check( ! str_contains( $safe, $forbidden ), 'KSES failed: ' . $forbidden );
}
$check( str_contains( $safe, 'Safe label' ), 'Safe alt lost' );
echo "PASS native image block, core More boundary, responsive attributes, captions, KSES; no content writes.\n";
