<?php
/**
 * Title: On The Air Welcome Strip
 * Slug: tacobout/on-the-air
 * Categories: featured
 * Inserter: true
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$mascot_url = get_template_directory_uri() . '/assets/brand/telly_pose_01_on_the_mic.png';
?>
<!-- wp:group {"className":"tacobout-on-the-air-strip","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-on-the-air-strip">
	<div class="tacobout-on-the-air-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_url ); ?>" alt="Telly on the microphone" class="mascot-sticker tacobout-on-the-air-mascot" />
	</div>
	<div class="tacobout-on-the-air-content">
		<span class="tacobout-on-the-air-badge">
			<svg class="tacobout-badge-icon" viewBox="0 0 24 24" fill="currentColor" width="13" height="13" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
			ON THE AIR
		</span>
		<p class="tacobout-on-the-air-text">Hot takes, wrestling rabbit holes, free-streaming deep dives &amp; life on the open web. Welcome to taco ‘bout it—Schwegler’s corner for TV commentary, chaotic internet culture, and fediverse experiments.</p>
	</div>
</div>
<!-- /wp:group -->
