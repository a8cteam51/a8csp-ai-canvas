<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\Mcp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the MCP component outside WordPress: its gate follows MCP Adapter's presence, its
 * hook phase stages the abilities and the server, and the abilities cannot register before the
 * readiness phase has wired the page and media operations.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Mcp\Component::class )]
#[UsesClass( Mcp\Media::class )]
#[UsesClass( Mcp\Pages::class )]
final class McpComponentTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Starts each test with an empty hook-registration ledger.
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
	 * Without MCP Adapter the gate is closed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_should_load_is_false_without_the_adapter(): void {
		self::assertFalse( Mcp\Component::should_load() );
	}

	/**
	 * With MCP Adapter's class present the gate is open. The stand-in class is declared here, so
	 * the test runs in its own process and the class cannot leak into the closed-gate proofs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_should_load_is_true_with_the_adapter(): void {
		\class_alias( \stdClass::class, 'WP\MCP\Core\McpAdapter' );

		self::assertTrue( Mcp\Component::should_load() );
	}

	/**
	 * The hook phase stages the category, the abilities, and the server onto their own hooks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_stages_the_abilities_and_the_server(): void {
		$component = new Mcp\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame( array( 'wp_abilities_api_categories_init', 'wp_abilities_api_init', 'mcp_adapter_init' ), $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	/**
	 * Registering the abilities before the readiness phase fails loud instead of registering
	 * tools with no operations behind them.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_abilities_cannot_register_before_initialization(): void {
		$this->expectException( \LogicException::class );

		( new Mcp\Component() )->register_abilities();
	}

	// endregion.
}
