<?php
/**
 * Title: Image Post
 * Slug: tacobout/image
 * Categories: gallery
 * Description: A photo-centric layout for Tumblog style posts.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- wp:group {"style":{"spacing":{"padding":{"bottom":"2rem"}}}} -->
<div class="wp-block-group" style="padding-bottom:2rem">
    <!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-rounded"} -->
    <figure class="wp-block-image size-large is-style-rounded"><img src="https://s.w.org/images/core/5.8/art-deco-2.jpg" alt="Sample Image"/></figure>
    <!-- /wp:image -->
    <!-- wp:paragraph -->
    <p>A caption or story about the photo above.</p>
    <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
