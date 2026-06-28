<?php
/**
 * Title: Podcast Episode
 * Slug: tacobout/podcast
 * Categories: audio
 * Description: A layout designed for podcast episodes with an audio player, host/guest info, and show notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"2rem","right":"2rem","bottom":"2rem","left":"2rem"}},"border":{"radius":"8px"}},"backgroundColor":"surface"} -->
<div class="wp-block-group has-surface-background-color has-background" style="border-radius:8px;padding-top:2rem;padding-right:2rem;padding-bottom:2rem;padding-left:2rem">
    
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Episode Title</h2>
    <!-- /wp:heading -->

    <!-- wp:audio -->
    <figure class="wp-block-audio"><audio controls src=""></audio></figure>
    <!-- /wp:audio -->

    <!-- wp:spacer {"height":"1.5rem"} -->
    <div style="height:1.5rem" aria-hidden="true" class="wp-block-spacer"></div>
    <!-- /wp:spacer -->

    <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"},"style":{"spacing":{"padding":{"top":"1rem","bottom":"1rem"}},"border":{"top":{"color":"var(--wp--preset--color--border)","width":"1px"},"bottom":{"color":"var(--wp--preset--color--border)","width":"1px"}}}} -->
    <div class="wp-block-group" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:1rem;padding-bottom:1rem">
        <!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
        <p style="font-weight:600">Hosted by: Tacobout Admin</p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph -->
        <p> | </p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
        <p style="font-weight:600">Guest: Special Guest</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <!-- wp:spacer {"height":"1.5rem"} -->
    <div style="height:1.5rem" aria-hidden="true" class="wp-block-spacer"></div>
    <!-- /wp:spacer -->

    <!-- wp:heading {"level":3} -->
    <h3 class="wp-block-heading">Show Notes</h3>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>In this episode, we discuss...</p>
    <!-- /wp:paragraph -->

    <!-- wp:list -->
    <ul class="wp-block-list">
        <!-- wp:list-item -->
        <li><a href="#">Mentioned Link 1</a></li>
        <!-- /wp:list-item -->
        <!-- wp:list-item -->
        <li><a href="#">Mentioned Link 2</a></li>
        <!-- /wp:list-item -->
    </ul>
    <!-- /wp:list -->
</div>
<!-- /wp:group -->
