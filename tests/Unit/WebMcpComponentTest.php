<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\WebMcp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the WebMCP component outside WordPress: its hook phase stages the tools script for
 * the block editor.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( WebMcp\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class WebMcpComponentTest extends TestCase {
	// region TESTS.

	/**
	 * The hook phase stages the tools script, and nothing else.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_stages_the_editor_script(): void {
		$GLOBALS['a8csp_ai_canvas_test_hooks'] = array();

		$component = new WebMcp\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame( array( 'enqueue_block_editor_assets' ), $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	// endregion.
}
