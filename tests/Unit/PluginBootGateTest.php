<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\Blocks;
use A8C\SpecialProjects\PluginTemplate\ComponentCollection;
use A8C\SpecialProjects\PluginTemplate\Integrations;
use A8C\SpecialProjects\PluginTemplate\Plugin;
use A8C\SpecialProjects\PluginTemplate\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real `Plugin::boot()` pipeline outside WordPress through the recording hook
 * stubs — the plugin-wide host gate latches an un-booted plugin behind an explanatory notice,
 * open-gate components register their hooks once the host is present, a closed-gate integration
 * registers nothing, and a second boot is a no-op.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Plugin::class )]
#[UsesClass( AbstractComponent::class )]
#[UsesClass( Blocks\Component::class )]
#[UsesClass( ComponentCollection::class )]
#[UsesClass( Settings\Component::class )]
#[UsesClass( Integrations\Component::class )]
#[UsesClass( Integrations\WooCommerceSubscriptions\Component::class )]
#[UsesClass( Integrations\WooPayments::class )]
final class PluginBootGateTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Loads the canned plugin metadata and the notice-rendering stand-ins the notice proofs invoke.
	 * Both load here rather than globally: the metadata stub would collide with the real reader
	 * PluginMetadataCacheTest defines in its own process. The WooCommerce host stand-ins load per
	 * test instead, so the host-absent proofs stay meaningful.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/plugin-metadata-stubs.php';
		require_once __DIR__ . '/wp-notice-stubs.php';
	}

	/**
	 * Starts each test with empty hook ledgers and a capable user.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_template_test_hooks']          = array();
		$GLOBALS['a8csp_template_test_hook_callbacks'] = array();
		$GLOBALS['a8csp_template_test_user_can']       = true;
	}

	// endregion.

	// region TESTS.

	/**
	 * Without the WooCommerce host, the boot latches un-booted behind the explanatory notice and
	 * constructs nothing — and stays that way: a second boot cannot retry the gate. The staged
	 * notice names the missing host, and renders nothing to a user who cannot activate plugins.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_boot_without_the_host_stays_unbooted(): void {
		$plugin = new Plugin();
		$plugin->boot();

		self::assertFalse( $plugin->is_booted() );
		self::assertSame( array( 'all_admin_notices' ), $GLOBALS['a8csp_template_test_hooks'] );

		$plugin->boot();

		self::assertFalse( $plugin->is_booted() );
		self::assertSame( array( 'all_admin_notices' ), $GLOBALS['a8csp_template_test_hooks'] );

		$notice = $this->staged_notice();
		self::assertStringContainsString( 'requires WooCommerce to be installed', $this->render_notice( $notice ) );

		$GLOBALS['a8csp_template_test_user_can'] = false;
		self::assertSame( '', $this->render_notice( $notice ), 'the notice renders nothing for a user without the capability' );
	}

	/**
	 * With the host present but below the header-declared floor, the boot latches un-booted behind
	 * the below-floor notice and constructs nothing. The staged notice names the floor and the
	 * running version, and renders nothing to a user without the WooCommerce capability.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_boot_below_the_host_floor_stays_unbooted(): void {
		\define( 'WC_VERSION', '11.0.0' );
		require_once __DIR__ . '/wc-host-stubs.php';

		$plugin = new Plugin();
		$plugin->boot();

		self::assertFalse( $plugin->is_booted() );
		self::assertSame( array( 'all_admin_notices' ), $GLOBALS['a8csp_template_test_hooks'] );

		$notice   = $this->staged_notice();
		$rendered = $this->render_notice( $notice );
		self::assertStringContainsString( 'or newer', $rendered );
		self::assertStringContainsString( (string) a8csp_template_get_plugin_metadata( 'WC requires at least' ), $rendered );
		self::assertStringContainsString( \constant( 'WC_VERSION' ), $rendered );

		$GLOBALS['a8csp_template_test_user_can'] = false;
		self::assertSame( '', $this->render_notice( $notice ), 'the notice renders nothing for a user without the capability' );
	}

	/**
	 * With the host at the floor, the boot flag reads false before the pipeline and true after it;
	 * open-gate components register their hooks while the Subscriptions-gated integration registers
	 * nothing when its companion is absent.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_boot_initializes_open_gate_components_and_skips_closed_gates(): void {
		require_once __DIR__ . '/wc-host-stubs.php';

		$plugin = new Plugin();
		self::assertFalse( $plugin->is_booted() );

		$plugin->boot();
		self::assertTrue( $plugin->is_booted() );

		self::assertContains( 'init', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'enqueue_block_editor_assets', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'admin_init', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_sections_advanced', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_settings_advanced', $GLOBALS['a8csp_template_test_hooks'] );

		self::assertNotContains( 'all_admin_notices', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertNotContains( 'wcpay_metadata_from_order', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertNotContains( 'woocommerce_subscriptions_product_price_string', $GLOBALS['a8csp_template_test_hooks'] );
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
		require_once __DIR__ . '/wc-host-stubs.php';

		$plugin = new Plugin();
		$plugin->boot();

		$hook_count = \count( $GLOBALS['a8csp_template_test_hooks'] );

		$plugin->boot();

		self::assertCount( $hook_count, $GLOBALS['a8csp_template_test_hooks'] );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Fetches the single admin-notice callback the failed gate staged on `all_admin_notices`.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  callable
	 */
	private function staged_notice(): callable {
		$callbacks = $GLOBALS['a8csp_template_test_hook_callbacks']['all_admin_notices'] ?? array();
		self::assertCount( 1, $callbacks, 'the failed gate must stage exactly one admin notice' );

		return $callbacks[0];
	}

	/**
	 * Invokes a staged notice callback under output buffering and returns what it rendered.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   callable $notice The staged notice callback.
	 *
	 * @return  string
	 */
	private function render_notice( callable $notice ): string {
		\ob_start();
		$notice();

		return (string) \ob_get_clean();
	}

	// endregion.
}
