<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the settings component outside WordPress: the component carries no gate of its own —
 * the plugin-wide host gate guarantees WooCommerce — and registers both of its surfaces in the
 * hook phase.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Settings\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class SettingsComponentTest extends TestCase {
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
	 * The component is core functionality behind the plugin-wide host gate, so its own gate is
	 * always open.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_should_load_is_always_true(): void {
		self::assertTrue( Settings\Component::should_load() );
	}

	/**
	 * The hook phase registers both surfaces: the Settings API staging on `admin_init` and the
	 * two WooCommerce section filters.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_registers_both_settings_surfaces(): void {
		$component = new Settings\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertContains( 'admin_init', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_sections_advanced', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_settings_advanced', $GLOBALS['a8csp_template_test_hooks'] );
	}

	// endregion.
}
