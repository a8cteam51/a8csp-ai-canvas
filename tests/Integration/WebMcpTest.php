<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Tests\Integration;

use A8C\SpecialProjects\AICanvasWebMCP\Templates;
use A8C\SpecialProjects\AICanvasWebMCP\WebMcp;
use PHPUnit\Framework\TestCase;

/**
 * Verifies where the tools script is enqueued with the plugin active: in the block editor of a
 * page, with the page templates it offers the agent, and nowhere else.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class WebMcpTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Loads the screen API, which WordPress loads in the admin only and the bootstrap's
	 * `wp-load.php` therefore leaves out.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
	}

	/**
	 * Takes the tools script back off the queue, and leaves the admin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		wp_dequeue_script( 'a8csp-ai-canvas-webmcp-webmcp' );
		set_current_screen( 'front' );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * In the block editor of a page the tools script is enqueued, after the editor packages its
	 * tools read and change the page through, with the templates handed to it before it runs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_script_is_enqueued_in_the_page_editor(): void {
		self::open_the_block_editor( 'page' );

		( new WebMcp\Component() )->enqueue_editor_script();

		self::assertTrue( wp_script_is( 'a8csp-ai-canvas-webmcp-webmcp', 'enqueued' ) );

		$script = wp_scripts()->registered['a8csp-ai-canvas-webmcp-webmcp'] ?? null;
		self::assertInstanceOf( \_WP_Dependency::class, $script );
		self::assertContains( 'wp-editor', $script->deps );
		self::assertContains( 'wp-blocks', $script->deps );
		self::assertContains( 'wp-api-fetch', $script->deps );

		$before = \implode( "\n", (array) wp_scripts()->get_data( 'a8csp-ai-canvas-webmcp-webmcp', 'before' ) );
		self::assertStringContainsString( (string) wp_json_encode( array( 'templates' => Templates\Component::SLUGS ) ), $before );
	}

	/**
	 * The block editor of a post gets no tools: they build a page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_script_is_not_enqueued_for_a_post(): void {
		self::open_the_block_editor( 'post' );

		( new WebMcp\Component() )->enqueue_editor_script();

		self::assertFalse( wp_script_is( 'a8csp-ai-canvas-webmcp-webmcp', 'enqueued' ) );
	}

	/**
	 * Outside the editor, with no screen at all or the front end's, there are no tools.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_script_is_not_enqueued_outside_the_editor(): void {
		( new WebMcp\Component() )->enqueue_editor_script();

		self::assertFalse( wp_script_is( 'a8csp-ai-canvas-webmcp-webmcp', 'enqueued' ) );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Makes the current screen the block editor of the given post type.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $post_type The post type.
	 *
	 * @return  void
	 */
	private static function open_the_block_editor( string $post_type ): void {
		set_current_screen( $post_type );

		$screen = get_current_screen();
		self::assertInstanceOf( \WP_Screen::class, $screen );
		$screen->is_block_editor( true );
	}

	// endregion.
}
