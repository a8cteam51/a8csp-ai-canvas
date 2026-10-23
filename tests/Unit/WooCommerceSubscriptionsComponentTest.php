<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\Integrations\WooCommerceSubscriptions\Component;
use A8C\SpecialProjects\PluginTemplate\Integrations\WooCommerceSubscriptions\PriceNote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the grown WooCommerce Subscriptions integration outside WordPress: the readiness
 * phase wires the collaborator without registering anything, the hook phase attaches both the
 * component's and the collaborator's callbacks, a hook phase without a readiness phase fails loud,
 * the collaborator wraps the price string, and the settings filter inserts its example row on the
 * plugin's own section only. Companion-gated paths run in separate processes so the stand-in never
 * leaks into the shared process.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Component::class )]
#[UsesClass( PriceNote::class )]
final class WooCommerceSubscriptionsComponentTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Loads the i18n and formatting stand-ins the price-note and settings-row proofs render through.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-notice-stubs.php';
	}

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
	 * Without the Subscriptions companion, the gate reports the integration as not needed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_should_load_is_false_without_the_companion(): void {
		self::assertFalse( Component::should_load() );
	}

	/**
	 * The readiness phase wires the collaborator without registering anything; the hook phase
	 * attaches the component's settings filter and the collaborator's price-string callback.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_hook_phase_attaches_component_and_collaborator_callbacks(): void {
		require_once __DIR__ . '/wcs-stubs.php';

		self::assertTrue( Component::should_load() );

		$component = new Component();

		$component->initialize();
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );

		$component->register_hooks();
		self::assertContains( 'woocommerce_get_settings_advanced', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_subscriptions_product_price_string', $GLOBALS['a8csp_template_test_hooks'] );
	}

	/**
	 * A hook phase without a readiness phase fails loud instead of attaching a half-built
	 * component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_hook_phase_before_readiness_fails_loud(): void {
		$this->expectException( \LogicException::class );

		( new Component() )->register_hooks();
	}

	/**
	 * The collaborator appends its example note to the companion-generated price string, keeping the
	 * original string intact.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_price_note_append_wraps_the_price_string(): void {
		$note = ( new PriceNote() )->append( '$5.00 / month' );

		self::assertStringContainsString( '$5.00 / month', $note );
		self::assertStringContainsString( 'example note', $note );
	}

	/**
	 * On the plugin's own Advanced section, `add_settings` inserts the Subscriptions example row and
	 * keeps the peer's trailing `sectionend` row last so WooCommerce still closes the section.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_add_settings_inserts_the_example_row_before_the_section_end(): void {
		$section_end = array(
			'type' => 'sectionend',
			'id'   => 'a8csp_template_wc_example_section',
		);
		$rows        = array(
			array(
				'id'   => 'a8csp_template_wc_example_option',
				'type' => 'text',
			),
			$section_end,
		);

		$result = ( new Component() )->add_settings( $rows, 'a8csp_template' );

		self::assertContains( 'a8csp_template_wcs_example_option', \array_column( $result, 'id' ) );
		self::assertSame( $section_end, \end( $result ), 'the section-end row must stay last so WooCommerce closes the section' );
	}

	/**
	 * On any other section, `add_settings` returns the rows untouched — the integration owns only
	 * its own section.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_add_settings_leaves_other_sections_untouched(): void {
		$rows = array(
			array(
				'id'   => 'foreign_option',
				'type' => 'text',
			),
		);

		self::assertSame( $rows, ( new Component() )->add_settings( $rows, 'some_other_section' ) );
	}

	// endregion.
}
