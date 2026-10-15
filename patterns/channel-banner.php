<?php
/**
 * Title: Channel Banner Card
 * Slug: tacobout/channel-banner
 * Categories: featured
 * Inserter: true
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$term = get_queried_object();
$slug = ( $term && isset( $term->slug ) ) ? $term->slug : '';
$raw_name = ( $term && isset( $term->name ) ) ? $term->name : '';

$channels = array(
	'tv' => array(
		'headline' => 'TV & Streaming',
		'subcopy'  => 'From Tubi rabbit holes and Revry drag competitions to cooking shows beyond the reruns.',
		'mascot'   => 'assets/brand/telly_pose_03_side_eye.png',
		'trove_label' => 'See all logged TV Shows in Trove',
		'trove_url'   => 'https://trove.schweg.xyz/tv_shows',
	),
	'wrestling' => array(
		'headline' => 'Wrestling',
		'subcopy'  => 'Workrate, long-term booking payoffs, and the wonderfully absurd theater of pro wrestling.',
		'mascot'   => 'assets/brand/telly_pose_03_side_eye.png',
		'trove_label' => 'Explore Trove',
		'trove_url'   => 'https://trove.schweg.xyz',
	),
	'big-brother' => array(
		'headline' => 'Big Brother',
		'subcopy'  => 'Blindsides, sloppy HOH reigns, and late-night houseguest overthinking.',
		'mascot'   => 'assets/brand/telly_pose_03_side_eye.png',
		'trove_label' => 'See all logged TV Shows in Trove',
		'trove_url'   => 'https://trove.schweg.xyz/tv_shows',
	),
	'food' => array(
		'headline' => 'Food',
		'subcopy'  => 'Tastemade marathons, kitchen experiments, and things worth eating.',
		'mascot'   => 'assets/brand/telly_pose_02_taco_break.png',
		'trove_label' => 'Explore Trove',
		'trove_url'   => 'https://trove.schweg.xyz',
	),
);

$channel = $channels[ $slug ] ?? array(
	'headline' => $raw_name ? $raw_name : 'Archive Channel',
	'subcopy'  => ( $term && ! empty( $term->description ) ) ? $term->description : 'Dispatches, commentary, and media logs from the open web.',
	'mascot'   => 'assets/brand/telly_pose_01_on_the_mic.png',
	'trove_label' => 'Explore Trove',
	'trove_url'   => 'https://trove.schweg.xyz',
);

if ( $term && ! empty( $term->description ) ) {
	$channel['subcopy'] = $term->description;
}

$mascot_url = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), get_theme_file_uri( $channel['mascot'] ) );
?>
<!-- wp:group {"className":"tacobout-channel-banner","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-channel-banner">
	<div class="tacobout-channel-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_url ); ?>" alt="" class="mascot-sticker tacobout-channel-mascot" />
	</div>
	<div class="tacobout-channel-content">
		<span class="tacobout-channel-badge">
			<svg class="tacobout-badge-icon" viewBox="0 0 24 24" fill="currentColor" width="12" height="12" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
			CHANNEL ARCHIVE
		</span>
		<h1 class="tacobout-channel-headline"><?php echo esc_html( $channel['headline'] ); ?></h1>
		<p class="tacobout-channel-subcopy"><?php echo esc_html( $channel['subcopy'] ); ?></p>
		<div class="tacobout-channel-actions">
			<a href="<?php echo esc_url( $channel['trove_url'] ); ?>" class="tacobout-sister-nav-pill">
				<?php echo esc_html( $channel['trove_label'] ); ?>
			</a>
		</div>
	</div>
</div>
<!-- /wp:group -->
