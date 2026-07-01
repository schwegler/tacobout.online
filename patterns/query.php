<?php
/**
 * Title: Query
 * Slug: tacobout/query
 * Categories: query
 * Inserter: false
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- wp:post-template {"className":"tacobout-magazine-grid"} -->

	<!-- wp:group {"className":"tacobout-card-inner","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0.75rem"}}} -->
	<div class="wp-block-group tacobout-card-inner">
		<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","style":{"border":{"radius":"12px"}}} /-->
		<!-- wp:template-part {"slug":"post-meta"} /-->
		<!-- wp:post-title {"isLink":true,"level":2,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--x-large)","lineHeight":"1.2"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->
		<!-- wp:post-excerpt {"moreText":"Read more →","showMoreOnNewLine":false,"excerptLength":22} /-->
		<!-- wp:post-content /-->
	</div>
	<!-- /wp:group -->

<!-- /wp:post-template -->

<!-- wp:spacer {"height":"var:preset|spacing|50"} -->
<div style="height:var(--wp--preset--spacing--50)" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
	<!-- wp:query-pagination-previous /-->
	<!-- wp:query-pagination-numbers /-->
	<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"textColor":"muted","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
<p class="has-muted-color has-text-color" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">No results found. Try searching for something else.</p>
<!-- /wp:paragraph -->
<!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search...","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true} /-->
<!-- /wp:query-no-results -->
