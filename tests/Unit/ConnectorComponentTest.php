<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\Connector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the connector component outside WordPress: its gate is always open, its hook phase
 * stages the connector and its card onto core's Connectors hooks, and it keeps core's connectors
 * script module among the screen's dependencies without disturbing the others.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Connector\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class ConnectorComponentTest extends TestCase {
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
	 * The card has no environmental dependency beyond the WordPress floor, so the gate is always
	 * open.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_should_load_is_always_true(): void {
		self::assertTrue( Connector\Component::should_load() );
	}

	/**
	 * The hook phase stages the connector's registration, its card, and the module dependency.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_stages_the_connector_and_its_card(): void {
		$component = new Connector\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame(
			array( 'wp_connectors_init', 'options-connectors-wp-admin_init', 'options-connectors-wp-admin_boot_dependencies' ),
			$GLOBALS['a8csp_ai_canvas_test_hooks']
		);
	}

	/**
	 * Core's connectors script module is appended to the screen's dependencies as a dynamic
	 * import, after whatever the screen already depends on.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_connectors_module_is_appended_to_the_dependencies(): void {
		$boot = array(
			'import' => 'static',
			'id'     => '@wordpress/boot',
		);

		$dependencies = ( new Connector\Component() )->require_connectors_module( array( $boot ) );

		self::assertSame(
			array(
				$boot,
				array(
					'import' => 'dynamic',
					'id'     => '@wordpress/connectors',
				),
			),
			$dependencies
		);
	}

	// endregion.
}
