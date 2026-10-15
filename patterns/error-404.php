<?php
/**
 * Title: 404 Error Dead Air Recovery
 * Slug: tacobout/error-404
 * Categories: featured
 * Inserter: false
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$mascot_side_eye = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), get_theme_file_uri( 'assets/brand/telly_pose_03_side_eye.png' ) );
?>
<!-- wp:group {"className":"tacobout-error-card","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-error-card">
	<div class="tacobout-error-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_side_eye ); ?>" alt="" class="mascot-sticker tacobout-error-mascot" />
	</div>
	<div class="tacobout-error-content">
		<span class="tacobout-error-channel-badge">
			<svg class="tacobout-badge-icon" viewBox="0 0 24 24" fill="currentColor" width="13" height="13" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
			BROADCAST INTERRUPTED
		</span>
		<h1 class="tacobout-error-title">Dead air on this channel.</h1>
		<p class="tacobout-error-text">This page may have moved. Search the site or return to the latest posts.</p>
		<div class="tacobout-error-actions">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tacobout-error-primary-btn">
				<svg class="tacobout-action-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				Back to latest posts
			</a>
			<a href="https://trove.schweg.xyz" class="tacobout-sister-nav-pill">Explore Trove</a>
		</div>
	</div>
</div>
<!-- /wp:group -->

<!-- wp:spacer {"height":"var:preset|spacing|40"} -->
<div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search the site…","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true} /-->
