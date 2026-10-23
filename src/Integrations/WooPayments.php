<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Integrations;

use A8C\SpecialProjects\AICanvasWebMCP\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * The WooPayments integration: adds example metadata to the payment data WooPayments builds
 * from an order, when the WooPayments plugin is active.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class WooPayments extends AbstractComponent {
	// region METHODS

	/**
	 * Whether WooPayments is active; once constructed, the integration may assume it is.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	#[\Override]
	public static function should_load(): bool {
		// WooPayments declares its main class on `plugins_loaded` priority 11, after this plugin
		// boots at 10; its main file defines this constant as soon as it is included.
		return \defined( 'WCPAY_PLUGIN_FILE' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'wcpay_metadata_from_order', array( $this, 'add_order_metadata' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds a demonstration entry to the payment metadata WooPayments generates from an order.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $metadata The payment metadata generated from the order.
	 *
	 * @return  array<string, mixed>
	 */
	public function add_order_metadata( array $metadata ): array {
		$metadata['a8csp_ai_webmcp_example'] = 'demonstration-value';

		return $metadata;
	}

	// endregion
}
