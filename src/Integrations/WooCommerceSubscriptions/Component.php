<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Integrations\WooCommerceSubscriptions;

use A8C\SpecialProjects\AICanvasWebMCP\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the WooCommerce Subscriptions integration, gated on the Subscriptions plugin being
 * active.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @see footprint.php
 */
final class Component implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The feature's wired collaborator, available from `initialize()` onward.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PriceNote|null
	 */
	private ?PriceNote $price_note = null;

	// endregion

	// region METHODS

	/**
	 * Whether WooCommerce Subscriptions is active; once constructed, the integration may assume it is.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	#[\Override]
	public static function should_load(): bool {
		return \class_exists( 'WC_Subscriptions' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function initialize(): void {
		$this->price_note = new PriceNote();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \LogicException If the hook phase runs before initialization.
	 */
	#[\Override]
	public function register_hooks(): void {
		$price_note = $this->price_note ?? throw new \LogicException( 'WooCommerce Subscriptions integration hooked before initialization.' );

		// Priority 20: the core section's rows exist by the time this filter runs.
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'add_settings' ), 20, 2 );
		add_filter( 'woocommerce_subscriptions_product_price_string', array( $price_note, 'append' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Appends the Subscriptions example row to the plugin's Advanced section, keeping the peer's
	 * trailing `sectionend` row last so WooCommerce closes the section after this row too.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int, array<string, mixed>> $settings        The settings rows for the current
	 *                                                            Advanced section.
	 * @param   string                           $current_section The current Advanced section slug.
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public function add_settings( array $settings, string $current_section ): array {
		if ( 'a8csp_ai_webmcp' !== $current_section ) {
			return $settings;
		}

		$subscriptions_row = array(
			'title' => __( 'Subscriptions example option', 'a8csp-ai-canvas-webmcp' ),
			'desc'  => __( 'A persisted example setting owned by the WooCommerce Subscriptions integration.', 'a8csp-ai-canvas-webmcp' ),
			// Listed in the `footprint.php` manifest; the line there goes with this component when it is deleted.
			'id'    => 'a8csp_ai_webmcp_wcs_example_option',
			'type'  => 'text',
		);
		\array_splice( $settings, -1, 0, array( $subscriptions_row ) );

		return $settings;
	}

	// endregion
}
