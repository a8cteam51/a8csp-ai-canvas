<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Proves the plugin-wide host gate in Plugin::boot(): with WooCommerce inactive the boot latches
 * un-booted for the request — no component constructs, the block does not register, no settings
 * wiring runs — and the "requires WooCommerce" notice is staged instead. The test is meaningful
 * only with WooCommerce deactivated, so it self-skips in the standard `composer test:integration`
 * run (WooCommerce is active there) — see tests/README.md for how to exercise it against a
 * WooCommerce-less runtime.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PluginBootWithoutWooCommerceTest extends TestCase {
	// region TESTS.

	/**
	 * With WooCommerce inactive, the requirements gate still passes and the boot hook still runs,
	 * but the host gate latches the plugin un-booted: the block is not registered, Settings wires
	 * nothing, no WooCommerce filters exist, and the missing-host notice is staged on
	 * `all_admin_notices`.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_plugin_stays_unbooted_without_woocommerce(): void {
		if ( \class_exists( 'WooCommerce' ) ) {
			self::markTestSkipped( 'This proof only runs against a WooCommerce-less runtime; WooCommerce is active in this run.' );
		}

		self::assertTrue( a8csp_template_validate_requirements() );
		self::assertTrue( \function_exists( 'a8csp_template_plugin' ) );

		self::assertFalse( a8csp_template_plugin()->is_booted() );
		// The gate's notice callback is an inline closure, so the assertion can only check that
		// something is staged on the hook, not the specific target.
		self::assertTrue( has_action( 'all_admin_notices' ) );

		$block_metadata = \json_decode(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local filesystem read of a tracked build artifact, not a remote resource.
			(string) \file_get_contents( \constant( 'A8CSP_TEMPLATE_DIR_PATH' ) . 'blocks/build/example-notice/block.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		self::assertFalse( \WP_Block_Type_Registry::get_instance()->is_registered( $block_metadata['name'] ) );
		self::assertFalse( has_filter( 'woocommerce_get_sections_advanced' ) );

		// `admin_init` fires where WordPress has loaded the wp-admin includes; the settings
		// template functions are loaded here to stand in for that context.
		require_once ABSPATH . 'wp-admin/includes/template.php';
		// Core's update checks also run on `admin_init` and call WordPress.org; detached, the proof
		// does not depend on the network.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		do_action( 'admin_init' );
		self::assertArrayNotHasKey( 'a8csp_template_example_option', get_registered_settings() );
	}

	// endregion.
}
