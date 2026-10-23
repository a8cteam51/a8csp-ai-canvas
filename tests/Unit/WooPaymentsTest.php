<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\Integrations\WooPayments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the WooPayments leaf integration outside WordPress: the gate follows the companion's
 * presence, the hook phase registers exactly the one metadata filter, and the filter callback is
 * a pure array amendment. Companion-gated paths run in separate processes so the stand-in never
 * leaks into the shared process.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( WooPayments::class )]
#[UsesClass( AbstractComponent::class )]
final class WooPaymentsTest extends TestCase {
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

		$GLOBALS['a8csp_template_test_hooks'] = array();
	}

	// endregion.

	// region TESTS.

	/**
	 * Without the WooPayments companion, the gate reports the integration as not needed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_should_load_is_false_without_the_companion(): void {
		self::assertFalse( WooPayments::should_load() );
	}

	/**
	 * With the companion's main file loaded, the gate opens and the hook phase registers exactly
	 * the one metadata filter.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_hook_phase_registers_the_metadata_filter(): void {
		require_once __DIR__ . '/wcpay-stubs.php';

		self::assertTrue( WooPayments::should_load() );

		$component = new WooPayments();

		$component->initialize();
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );

		$component->register_hooks();
		self::assertSame( array( 'wcpay_metadata_from_order' ), $GLOBALS['a8csp_template_test_hooks'] );
	}

	/**
	 * The metadata callback amends the passed array and preserves what WooPayments generated.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_metadata_callback_amends_the_generated_metadata(): void {
		$metadata = ( new WooPayments() )->add_order_metadata( array( 'existing' => 'entry' ) );

		self::assertSame( 'entry', $metadata['existing'] );
		self::assertSame( 'demonstration-value', $metadata['a8csp_template_example'] );
	}

	// endregion.
}
