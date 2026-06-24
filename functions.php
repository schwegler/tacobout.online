<?php
/**
 * Tacobout Social functions and definitions
 */

if ( ! function_exists( 'tacobout_support' ) ) :
	function tacobout_support() {
		// Enqueue editor styles.
		add_editor_style( 'style.css' );

		// Make theme available for translation.
		load_theme_textdomain( 'tacobout' );

        // Add support for core block visual styles.
        add_theme_support( 'wp-block-styles' );

        // Add support for post formats (Tumblog style).
        add_theme_support( 'post-formats', array(
            'status', // Microblog
            'image',  // Photo
            'video',  // Video
            'quote',
            'link',
            'audio'
        ) );

        // Enqueue frontend styles
        add_action( 'wp_enqueue_scripts', 'tacobout_enqueue_styles' );
	}
endif;
add_action( 'after_setup_theme', 'tacobout_support' );

function tacobout_enqueue_styles() {
    wp_enqueue_style(
        'tacobout-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );
}
