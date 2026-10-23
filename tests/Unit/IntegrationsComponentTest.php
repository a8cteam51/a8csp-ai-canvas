<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\ComponentCollection;
use A8C\SpecialProjects\PluginTemplate\Integrations\Component;
use A8C\SpecialProjects\PluginTemplate\Integrations\WooCommerceSubscriptions;
use A8C\SpecialProjects\PluginTemplate\Integrations\WooPayments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Integrations group root outside WordPress: children are gated statically before
 * construction, constructed and initialized inside the parent's readiness phase, and hooked only
 * when the parent forwards the hook phase. Both tests run in separate processes so the companion
 * stand-in never leaks into the shared process, where it would open the gate for other tests.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Component::class )]
#[UsesClass( AbstractComponent::class )]
#[UsesClass( ComponentCollection::class )]
#[UsesClass( WooCommerceSubscriptions\Component::class )]
#[UsesClass( WooCommerceSubscriptions\PriceNote::class )]
#[UsesClass( WooPayments::class )]
final class IntegrationsComponentTest extends TestCase {
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
	 * With every companion absent, the group runs both phases without constructing a child or
	 * registering a hook — the static gates close before construction.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_children_with_absent_companions_stay_unconstructed(): void {
		self::assertTrue( Component::should_load() );

		$component = new Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );
	}

	/**
	 * With the Subscriptions companion present, the readiness phase constructs and initializes the
	 * child without registering anything; the child's hooks appear only when the parent forwards
	 * the hook phase.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_hook_phase_forwards_to_surviving_children_only_after_readiness(): void {
		require_once __DIR__ . '/wcs-stubs.php';

		$component = new Component();

		$component->initialize();
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );

		$component->register_hooks();
		self::assertContains( 'woocommerce_get_settings_advanced', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_subscriptions_product_price_string', $GLOBALS['a8csp_template_test_hooks'] );
	}

	// endregion.
}
