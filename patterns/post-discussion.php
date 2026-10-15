<?php
/**
 * Title: Post Discussion and Fediverse Reply Card
 * Slug: tacobout/post-discussion
 * Categories: text
 * Inserter: true
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$mascot_break_url = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), get_theme_file_uri( 'assets/brand/telly_pose_02_taco_break.png' ) );
?>
<!-- wp:group {"className":"tacobout-discussion-card","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-discussion-card">
	<div class="tacobout-discussion-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_break_url ); ?>" alt="" class="mascot-sticker tacobout-discussion-mascot" />
	</div>
	<div class="tacobout-discussion-content">
		<p class="tacobout-discussion-author">Written by <strong>Schweg and Friends</strong> · Fuelled by tacos, games &amp; cats (Rose &amp; Violet).</p>
		<p class="tacobout-discussion-prompt">Keep the conversation going. Follow taco ’bout it on Bluesky or Mastodon, or share this post with your own thoughts.</p>
		<div class="tacobout-discussion-actions">
			<a href="https://bsky.app/profile/tacobout.online" class="tacobout-bluesky-cta" target="_blank" rel="noopener noreferrer">
				<svg class="tacobout-action-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566 1.01 1.5 1.5 1.5 3.5c0 1.942.5 7.5 1 9.5.6 2.4 2.5 3 4.5 2.5-3 1-4 3-2 5 2.5 2.5 6-1 7-4 1 3 4.5 6.5 7 4 2-2 1-4-2-5 2 .5 3.9-.1 4.5-2.5.5-2 1-7.558 1-9.5 0-2-1.066-2.49-3.702-.695C16.046 4.747 13.087 8.686 12 10.8z"/></svg>
				Follow on Bluesky →
			</a>
			<a href="https://mastodon.social/@blog@tacobout.online" class="tacobout-mastodon-cta" target="_blank" rel="noopener noreferrer">
				<svg class="tacobout-action-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><path d="M21.327 8.566c0-4.339-2.843-5.61-2.843-5.61-1.433-.658-3.894-.935-6.451-.956h-.063c-2.557.021-5.016.298-6.45.956 0 0-2.843 1.272-2.843 5.61 0 .993-.019 2.18.049 3.48.232 4.45 2.71 8.52 7.784 8.76 2.51.12 4.41-.62 4.41-.62l-.08-1.78s-1.84.58-3.9.52c-2.04-.06-4.17-.26-4.46-2.56a4.8 4.8 0 0 1-.06-.76c2.08.52 4.15.62 6.25.54 2.1-.08 4.14-.42 5.95-1.12 2.22-.86 3.12-2.79 3.25-4.52.13-1.73.1-4.08.1-4.08zm-3.67 5.86c0 .44-.36.8-.8.8s-.8-.36-.8-.8v-3.77c0-.82-.34-1.24-1.02-1.24-.76 0-1.14.49-1.14 1.47v2.12c0 .44-.36.8-.8.8s-.8-.36-.8-.8V8.92c0-.98-.38-1.47-1.14-1.47-.68 0-1.02.42-1.02 1.24v3.77c0 .44-.36.8-.8.8s-.8-.36-.8-.8V8.77c0-1.28.66-2.29 1.97-2.29.98 0 1.74.45 2.13 1.25.39-.8 1.15-1.25 2.13-1.25 1.31 0 1.97 1.01 1.97 2.29v5.65z"/></svg>
				Follow on Mastodon
			</a>
		</div>
	</div>
</div>
<!-- /wp:group -->
