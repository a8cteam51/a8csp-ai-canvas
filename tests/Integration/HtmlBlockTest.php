<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use A8C\SpecialProjects\AICanvas\HtmlBlock;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the Custom HTML block as WordPress registered it with the plugin active: it can be
 * aligned to the full width, and the script and stylesheet the editor needs for it are enqueued
 * there, and only there.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class HtmlBlockTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Takes the editor script and stylesheet back off the queue, and leaves the admin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		wp_dequeue_script( 'a8csp-ai-canvas-html-block' );
		wp_dequeue_style( 'a8csp-ai-canvas-html-block' );
		set_current_screen( 'front' );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * Core's Custom HTML block supports the full-width alignment and nothing wider than that, has
	 * the `align` attribute that comes with it, and keeps the supports it declares itself.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_html_block_can_be_aligned_to_the_full_width(): void {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'core/html' );

		self::assertInstanceOf( \WP_Block_Type::class, $block_type );
		self::assertSame( array( 'full' ), $block_type->supports['align'] ?? null );
		self::assertFalse( $block_type->supports['html'] ?? null );
		self::assertArrayHasKey( 'align', $block_type->attributes ?? array() );
	}

	/**
	 * A block that declares no alignment of its own is not given one.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_other_blocks_are_not_given_the_alignment(): void {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'core/shortcode' );

		self::assertInstanceOf( \WP_Block_Type::class, $block_type );
		self::assertArrayNotHasKey( 'align', $block_type->supports ?? array() );
	}

	/**
	 * A block comment carrying the alignment parses to a Custom HTML block with it, which is what
	 * the page tools check and what the editor reads.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_alignment_survives_a_parse_and_serialize_round_trip(): void {
		$content = '<!-- wp:html {"align":"full"} -->' . "\n" . '<div class="canvas-proof">Proof</div>' . "\n" . '<!-- /wp:html -->';
		$blocks  = parse_blocks( $content );

		self::assertSame( 'core/html', $blocks[0]['blockName'] );
		self::assertSame( 'full', $blocks[0]['attrs']['align'] ?? null );
		self::assertSame( $content, serialize_blocks( $blocks ) );
	}

	/**
	 * The editor script is enqueued for the block editor, after the hooks and compose packages it
	 * registers its filter with.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_editor_script_is_enqueued(): void {
		self::assertFalse( wp_script_is( 'a8csp-ai-canvas-html-block', 'enqueued' ) );

		( new HtmlBlock\Component() )->enqueue_editor_script();

		self::assertTrue( wp_script_is( 'a8csp-ai-canvas-html-block', 'enqueued' ) );

		$script = wp_scripts()->registered['a8csp-ai-canvas-html-block'] ?? null;
		self::assertInstanceOf( \_WP_Dependency::class, $script );
		self::assertContains( 'wp-hooks', $script->deps );
		self::assertContains( 'wp-compose', $script->deps );
	}

	/**
	 * The editor script depends on the packages the visual editor is built from, and has its
	 * translations set up: it is where every string of the visual editor lives.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_editor_script_carries_the_visual_editor(): void {
		( new HtmlBlock\Component() )->enqueue_editor_script();

		$script = wp_scripts()->registered['a8csp-ai-canvas-html-block'] ?? null;
		self::assertInstanceOf( \_WP_Dependency::class, $script );
		self::assertContains( 'wp-block-editor', $script->deps );
		self::assertContains( 'wp-components', $script->deps );
		self::assertContains( 'wp-i18n', $script->deps );
		self::assertSame( 'a8csp-ai-canvas', $script->textdomain );
	}

	/**
	 * The visual editor's stylesheet is enqueued in the admin, where the hook it is on feeds both
	 * the editor and the editor's canvas, with a right-to-left version to swap in.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_editor_style_is_enqueued_in_the_admin(): void {
		set_current_screen( 'post' );
		self::assertTrue( is_admin() );

		( new HtmlBlock\Component() )->enqueue_editor_style();

		self::assertTrue( wp_style_is( 'a8csp-ai-canvas-html-block', 'enqueued' ) );

		$style = wp_styles()->registered['a8csp-ai-canvas-html-block'] ?? null;
		self::assertInstanceOf( \_WP_Dependency::class, $style );
		self::assertStringEndsWith( 'assets/js/build/html-block.css', (string) $style->src );
		self::assertSame( 'replace', $style->extra['rtl'] ?? null );
	}

	/**
	 * The stylesheet stays off the front end, where the same hook fires and nothing is edited.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_editor_style_is_not_enqueued_on_the_front_end(): void {
		set_current_screen( 'front' );
		self::assertFalse( is_admin() );

		( new HtmlBlock\Component() )->enqueue_editor_style();

		self::assertFalse( wp_style_is( 'a8csp-ai-canvas-html-block', 'enqueued' ) );
	}

	// endregion.
}
