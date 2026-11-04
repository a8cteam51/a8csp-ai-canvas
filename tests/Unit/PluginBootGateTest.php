<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\ComponentCollection;
use A8C\SpecialProjects\AICanvas\HtmlBlock;
use A8C\SpecialProjects\AICanvas\Plugin;
use A8C\SpecialProjects\AICanvas\Templates;
use A8C\SpecialProjects\AICanvas\WebMcp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real `Plugin::boot()` pipeline outside WordPress through the recording hook
 * stubs — every component registers its hooks, and a second boot is a no-op.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Plugin::class )]
#[UsesClass( AbstractComponent::class )]
#[UsesClass( ComponentCollection::class )]
#[UsesClass( HtmlBlock\Component::class )]
#[UsesClass( Templates\Component::class )]
#[UsesClass( WebMcp\Component::class )]
final class PluginBootGateTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Starts each test with an empty hook ledger.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_ai_canvas_test_hooks'] = array();
	}

	// endregion.

	// region TESTS.

	/**
	 * The boot flag reads false before the pipeline and true after it, and the components have
	 * registered their hooks: the templates, the Custom HTML block's support and editor assets,
	 * and the tools script.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_boot_registers_every_components_hooks(): void {
		$plugin = new Plugin();
		self::assertFalse( $plugin->is_booted() );

		$plugin->boot();
		self::assertTrue( $plugin->is_booted() );

		self::assertContains( 'init', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'run_wptexturize', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'register_block_type_args', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'enqueue_block_editor_assets', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	/**
	 * A second boot on the same instance leaves the hook-registration ledger unchanged.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_second_boot_is_a_no_op(): void {
		$plugin = new Plugin();
		$plugin->boot();

		$hook_count = \count( $GLOBALS['a8csp_ai_canvas_test_hooks'] );

		$plugin->boot();

		self::assertCount( $hook_count, $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	// endregion.
}
