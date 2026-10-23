<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Integration;

use A8C\SpecialProjects\PluginTemplate\Plugin;
use A8C\SpecialProjects\PluginTemplate\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin boots on a supported runtime inside wp-env: the requirements gate
 * passes, the component registry runs, and the demo components wire and register their
 * WordPress and WooCommerce functionality. The cached accessor is idempotent.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PluginBootTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Removes the example option the persistence round-trip seeds, regardless of how the test
	 * finished, since this suite runs against a persistent wp-env database with no per-test
	 * transaction rollback (see tests/README.md).
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		delete_option( 'a8csp_template_example_option' );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * On an at-floor runtime the requirements gate passes, `plugins_loaded` is wired to the
	 * memoized root's `boot()` (the accessor constructs eagerly at include time, so the wiring
	 * check below resolves against the same instance), and by request time the pipeline has run
	 * the demo components far enough to register the block and expose the WooCommerce section and
	 * its persisted field. The base setting's callback registers it when called directly.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_plugin_boots_on_supported_runtime(): void {
		self::assertTrue( a8csp_template_validate_requirements() );
		self::assertTrue( \function_exists( 'a8csp_template_plugin' ) );
		self::assertNotFalse( has_action( 'plugins_loaded', array( a8csp_template_plugin(), 'boot' ) ) );
		self::assertInstanceOf( Plugin::class, a8csp_template_plugin() );
		self::assertTrue( a8csp_template_plugin()->is_booted() );

		$block_metadata = \json_decode(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local filesystem read of a tracked build artifact, not a remote resource.
			(string) \file_get_contents( \constant( 'A8CSP_TEMPLATE_DIR_PATH' ) . 'blocks/build/example-notice/block.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		self::assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $block_metadata['name'] ) );

		// register_settings() runs on `admin_init`, where WordPress has loaded the wp-admin includes;
		// the settings template functions are loaded here to stand in for that context. The call is
		// direct because firing `admin_init` would also run WooCommerce's wp-admin-only callbacks.
		require_once ABSPATH . 'wp-admin/includes/template.php';
		( new Settings\Component() )->register_settings();
		self::assertArrayHasKey( 'a8csp_template_example_option', get_registered_settings() );

		$sections = apply_filters( 'woocommerce_get_sections_advanced', array() );
		self::assertArrayHasKey( 'a8csp_template', $sections );

		$rows = apply_filters( 'woocommerce_get_settings_advanced', array(), 'a8csp_template' );
		self::assertContains( 'a8csp_template_wc_example_option', \array_column( $rows, 'id' ) );
	}

	/**
	 * The example option round-trips: a persisted value comes back through the typed reader, and
	 * the settings field renders it escaped into its `value` attribute. The sentinel's quote and
	 * ampersand survive `sanitize_text_field` but not escaping, so the raw form must not appear.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_example_option_round_trips_through_reader_and_field(): void {
		update_option( 'a8csp_template_example_option', 'audit"sentinel&' );

		self::assertSame( 'audit"sentinel&', a8csp_template_get_example_option() );

		\ob_start();
		( new Settings\Component() )->render_field();
		$field = (string) \ob_get_clean();

		self::assertStringContainsString( 'value="audit&quot;sentinel&amp;"', $field );
		self::assertStringNotContainsString( 'audit"sentinel', $field );
	}

	// endregion.
}
