<?php

class FunctionsTest extends \PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		global $wp_styles_enqueued;
		$wp_styles_enqueued = array();
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_tacobout_enqueue_styles() {
		global $wp_styles_enqueued;

		// Ensure array starts empty
		$this->assertEmpty( $wp_styles_enqueued );

		// Call the function
		tacobout_enqueue_styles();

		// Check if styles were enqueued
		$this->assertArrayHasKey( 'tacobout-style', $wp_styles_enqueued );
		$this->assertEquals( 'http://example.com/wp-content/themes/tacobout/style.css', $wp_styles_enqueued['tacobout-style']['src'] );
		$this->assertEquals( '1.0.0', $wp_styles_enqueued['tacobout-style']['ver'] );
		$this->assertEquals( array(), $wp_styles_enqueued['tacobout-style']['deps'] );

		$this->assertArrayHasKey( 'tacobout-google-fonts', $wp_styles_enqueued );
		$this->assertEquals( 'https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap', $wp_styles_enqueued['tacobout-google-fonts']['src'] );
		$this->assertNull( $wp_styles_enqueued['tacobout-google-fonts']['ver'] );
		$this->assertEquals( array(), $wp_styles_enqueued['tacobout-google-fonts']['deps'] );
	}

	public function test_tacobout_clear_saved_templates_already_cleared() {
		\Brain\Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'tacobout_templates_cleared_v2' )
			->andReturn( true );

		\Brain\Monkey\Functions\expect( 'get_posts' )->never();
		\Brain\Monkey\Functions\expect( 'wp_delete_post' )->never();
		\Brain\Monkey\Functions\expect( 'update_option' )->never();
		\Brain\Monkey\Functions\expect( 'get_stylesheet' )->never();

		tacobout_clear_saved_templates();

		// BrainMonkey expect functions handle assertions under the hood, but PHPUnit doesn't know this.
		// We can add a dummy assertion to prevent the risky test warning, or we can configure PHPUnit to ignore it.
		$this->assertTrue( true );
	}

	public function test_tacobout_clear_saved_templates_clears_templates() {
		\Brain\Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'tacobout_templates_cleared_v2' )
			->andReturn( false );

		\Brain\Monkey\Functions\expect( 'get_stylesheet' )
			->once()
			->andReturn( 'tacobout' );

		// Mock get_posts to return different results based on the arguments
		// Since functions.php uses 'fields' => 'ids', we return an array of IDs
		\Brain\Monkey\Functions\expect( 'get_posts' )
			->twice()
			->andReturnUsing(
				function ( $args ) {
					if ( $args['post_type'] === 'wp_template' ) {
						return array( 10, 20 );
					} elseif ( $args['post_type'] === 'wp_template_part' ) {
						return array( 30 );
					}
					return array();
				}
			);

		// We expect wp_delete_post to be called with the ID of each post returned by get_posts
		\Brain\Monkey\Functions\expect( 'wp_delete_post' )
			->times( 3 )
			->with( \Mockery::anyOf( 10, 20, 30 ), true );

		// We expect update_option to be called at the end
		\Brain\Monkey\Functions\expect( 'update_option' )
			->once()
			->with( 'tacobout_templates_cleared_v2', true );

		tacobout_clear_saved_templates();

		$this->assertTrue( true );
	}

	public function test_tacobout_get_memoized_post_format() {
		\Brain\Monkey\Functions\expect( 'get_post_format' )
			->once()
			->with( 42 )
			->andReturn( 'video' );

		// First call should query get_post_format
		$format1 = tacobout_get_memoized_post_format( 42 );
		$this->assertEquals( 'video', $format1 );

		// Second call should return cached format without calling get_post_format again
		$format2 = tacobout_get_memoized_post_format( 42 );
		$this->assertEquals( 'video', $format2 );
	}

	public function test_tacobout_get_interaction_count_cached() {
		\Brain\Monkey\Functions\expect( 'wp_cache_get' )
			->once()
			->with( 'tacobout_int_count_99', 'posts' )
			->andReturn( 5 );

		$count = tacobout_get_interaction_count( 99 );
		$this->assertEquals( 5, $count );
	}

	public function test_tacobout_get_total_published_posts_transient() {
		\Brain\Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'tacobout_total_published_posts' )
			->andReturn( 15 );

		$total = tacobout_get_total_published_posts();
		$this->assertEquals( 15, $total );
	}

	public function test_tacobout_enqueue_infinite_scroll_aborts_on_theme_preview() {
		$_GET['wp_theme_preview'] = '1';
		\Brain\Monkey\Functions\expect( 'is_admin' )->never();
		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->never();

		tacobout_enqueue_infinite_scroll();

		$this->assertTrue( true );
		unset( $_GET['wp_theme_preview'] );
	}

	public function test_tacobout_enqueue_infinite_scroll_aborts_in_admin() {
		\Brain\Monkey\Functions\expect( 'is_admin' )->once()->andReturn( true );
		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->never();

		tacobout_enqueue_infinite_scroll();

		$this->assertTrue( true );
	}

	public function test_tacobout_enqueue_infinite_scroll_enqueues_and_localizes() {
		\Brain\Monkey\Functions\expect( 'is_admin' )->once()->andReturn( false );
		\Brain\Monkey\Functions\expect( 'get_template_directory_uri' )->once()->andReturn( 'http://example.com' );

		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->once();

		\Brain\Monkey\Functions\expect( 'get_transient' )->once()->with( 'tacobout_total_published_posts' )->andReturn( 18 );

		\Brain\Monkey\Functions\expect( 'is_category' )->once()->andReturn( false );
		\Brain\Monkey\Functions\expect( 'is_tag' )->once()->andReturn( false );

		\Brain\Monkey\Functions\expect( 'rest_url' )->once()->andReturn( 'http://example.com/wp-json' );
		\Brain\Monkey\Functions\expect( 'esc_url_raw' )->once()->andReturn( 'http://example.com/wp-json' );
		\Brain\Monkey\Functions\expect( 'wp_create_nonce' )->once()->andReturn( 'nonce123' );
		\Brain\Monkey\Functions\expect( 'home_url' )->once()->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_url' )->once()->andReturn( 'http://example.com' );

		\Brain\Monkey\Functions\expect( 'wp_localize_script' )->once();

		tacobout_enqueue_infinite_scroll();

		$this->assertTrue( true );
	}

	public function test_tacobout_enqueue_infinite_scroll_category_context() {
		\Brain\Monkey\Functions\expect( 'is_admin' )->once()->andReturn( false );
		\Brain\Monkey\Functions\expect( 'get_template_directory_uri' )->andReturn( 'http://example.com' );

		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->once();

		\Brain\Monkey\Functions\expect( 'get_transient' )->once()->with( 'tacobout_total_published_posts' )->andReturn( 18 );

		\Brain\Monkey\Functions\expect( 'is_category' )->once()->andReturn( true );

		$mock_term          = new \stdClass();
		$mock_term->term_id = 42;
		$mock_term->name    = 'News';
		$mock_term->count   = 27; // 27 / 9 = 3 pages
		\Brain\Monkey\Functions\expect( 'get_queried_object' )->once()->andReturn( $mock_term );

		\Brain\Monkey\Functions\expect( 'rest_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_url_raw' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'wp_create_nonce' )->andReturn( 'nonce' );
		\Brain\Monkey\Functions\expect( 'home_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_html' )->once()->with( 'News' )->andReturn( 'News' );

		\Brain\Monkey\Functions\expect( 'wp_localize_script' )->once();

		tacobout_enqueue_infinite_scroll();

		$this->assertTrue( true );
	}

	public function test_tacobout_enqueue_infinite_scroll_tag_context() {
		\Brain\Monkey\Functions\expect( 'is_admin' )->once()->andReturn( false );
		\Brain\Monkey\Functions\expect( 'get_template_directory_uri' )->andReturn( 'http://example.com' );

		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->once();

		\Brain\Monkey\Functions\expect( 'get_transient' )->once()->with( 'tacobout_total_published_posts' )->andReturn( 18 );

		\Brain\Monkey\Functions\expect( 'is_category' )->once()->andReturn( false );
		\Brain\Monkey\Functions\expect( 'is_tag' )->once()->andReturn( true );

		$mock_term          = new \stdClass();
		$mock_term->term_id = 99;
		$mock_term->name    = 'Tech';
		$mock_term->count   = 10; // 10 / 9 = 2 pages
		\Brain\Monkey\Functions\expect( 'get_queried_object' )->once()->andReturn( $mock_term );

		\Brain\Monkey\Functions\expect( 'rest_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_url_raw' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'wp_create_nonce' )->andReturn( 'nonce' );
		\Brain\Monkey\Functions\expect( 'home_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_url' )->andReturn( 'http://example.com' );
		\Brain\Monkey\Functions\expect( 'esc_html' )->once()->with( 'Tech' )->andReturn( 'Tech' );

		\Brain\Monkey\Functions\expect( 'wp_localize_script' )->once();

		tacobout_enqueue_infinite_scroll();

		$this->assertTrue( true );
	}
}
