<?php
/**
 * Title: On The Air Welcome Strip
 * Slug: tacobout/on-the-air
 * Categories: featured
 * Inserter: true
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$mascot_url = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), get_theme_file_uri( 'assets/brand/telly_pose_01_on_the_mic.png' ) );
?>
<!-- wp:group {"className":"tacobout-on-the-air-strip","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-on-the-air-strip">
	<div class="tacobout-on-the-air-content">
		<h1 class="tacobout-welcome-title">Plenty to talk about.</h1>
		<p class="tacobout-on-the-air-text">Commentary, discoveries, and a well-earned side-eye.</p>
	</div>
	<p class="tacobout-welcome-note">Long reads. Quick finds. Open conversations.<br>Welcome to Schweg and Friends.</p>
	<div class="tacobout-on-the-air-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_url ); ?>" width="69" height="84" alt="" class="mascot-sticker tacobout-on-the-air-mascot" />
	</div>
</div>
<!-- /wp:group -->
