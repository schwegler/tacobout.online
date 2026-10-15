<?php
/**
 * Title: The Companion Vault Callout
 * Slug: tacobout/companion-vault
 * Categories: featured
 * Inserter: true
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$mascot_url = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), get_theme_file_uri( 'assets/brand/mascot_duo_telly_and_facet_transparent.png' ) );
?>
<!-- wp:group {"className":"tacobout-companion-vault-card","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group tacobout-companion-vault-card">
	<div class="tacobout-vault-mascot-wrap">
		<img src="<?php echo esc_url( $mascot_url ); ?>" width="1536" height="1024" alt="" class="mascot-sticker tacobout-vault-mascot" />
	</div>
	<div class="tacobout-vault-content">
		<span class="tacobout-vault-badge">
			<svg class="tacobout-badge-icon" viewBox="0 0 24 24" fill="currentColor" width="12" height="12" aria-hidden="true"><polygon points="6 3 18 3 22 9 12 22 2 9"/></svg>
			THE COLLECTION
		</span>
		<h2 class="tacobout-vault-headline">The collection continues on Trove.</h2>
		<p class="tacobout-vault-subcopy">Track movies, shows, comics, books, albums, and games. Organize your collection, log progress, and share reviews on Trove.</p>
		<div class="tacobout-vault-actions">
			<a href="https://trove.schweg.xyz" class="tacobout-sister-nav-pill" target="_blank" rel="noopener noreferrer">
				Explore Trove
			</a>
		</div>
	</div>
</div>
<!-- /wp:group -->
