<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\ComponentCollection;
use A8C\SpecialProjects\AICanvas\Connector;
use A8C\SpecialProjects\AICanvas\HtmlBlock;
use A8C\SpecialProjects\AICanvas\Mcp;
use A8C\SpecialProjects\AICanvas\PageAccess;
use A8C\SpecialProjects\AICanvas\Plugin;
use A8C\SpecialProjects\AICanvas\Templates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real `Plugin::boot()` pipeline outside WordPress through the recording hook
 * stubs — open-gate components register their hooks, the MCP component registers nothing while
 * MCP Adapter is absent, and a second boot is a no-op.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Plugin::class )]
#[UsesClass( AbstractComponent::class )]
#[UsesClass( ComponentCollection::class )]
#[UsesClass( Connector\Component::class )]
#[UsesClass( HtmlBlock\Component::class )]
#[UsesClass( Mcp\Component::class )]
#[UsesClass( PageAccess\Component::class )]
#[UsesClass( Templates\Component::class )]
final class PluginBootGateTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Starts each test with empty hook ledgers.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_ai_canvas_test_hooks']          = array();
		$GLOBALS['a8csp_ai_canvas_test_hook_callbacks'] = array();
	}

	// endregion.

	// region TESTS.

	/**
	 * The boot flag reads false before the pipeline and true after it; open-gate components
	 * register their hooks while the MCP component registers nothing when MCP Adapter is absent.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_boot_initializes_open_gate_components_and_skips_closed_gates(): void {
		$plugin = new Plugin();
		self::assertFalse( $plugin->is_booted() );

		$plugin->boot();
		self::assertTrue( $plugin->is_booted() );

		self::assertContains( 'init', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'run_wptexturize', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'wp_connectors_init', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertContains( 'manage_pages_columns', $GLOBALS['a8csp_ai_canvas_test_hooks'] );

		self::assertNotContains( 'wp_abilities_api_init', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
		self::assertNotContains( 'mcp_adapter_init', $GLOBALS['a8csp_ai_canvas_test_hooks'] );
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
