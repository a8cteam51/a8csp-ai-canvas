<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP;

\defined( 'ABSPATH' ) || exit;

/**
 * The plugin's composition root: assembles the top-level components and runs the boot pipeline.
 *
 * @internal
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * Add the plugin's top-level components here; they run through each phase in registration
	 * order.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array<int, class-string<ComponentInterface>>
	 */
	private const array COMPONENTS = array(
		Blocks\Component::class,
		Settings\Component::class,
		Integrations\Component::class,
	);

	/**
	 * Tri-state boot flag: null until `boot()` is first entered, false from entry until the hook
	 * phase completes — which also latches reentrant calls and post-failure retries into no-ops,
	 * since a half-attached boot must never be replayed — and true only on success.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     bool|null
	 */
	private ?bool $booted = null;

	// endregion

	// region METHODS

	/**
	 * Whether the boot pipeline completed successfully for this request.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	public function is_booted(): bool {
		return true === $this->booted;
	}

	// endregion

	// region HOOKS

	/**
	 * Runs the plugin's boot pipeline.
	 *
	 * A boot failure propagates uncaught — fail loud; the entry latch already guarantees it cannot
	 * be retried into duplicate hook registrations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function boot(): void {
		if ( null !== $this->booted ) {
			return;
		}

		$this->booted = false;
		if ( ! $this->meets_wc_requirements() ) {
			return;
		}

		$components = ComponentCollection::assemble( self::COMPONENTS );
		$components->initialize();
		$components->register_hooks();

		$this->booted = true;
	}

	// endregion

	// region HELPERS

	/**
	 * Whether the host requirements hold for this request: WooCommerce present and at the
	 * header-declared `WC requires at least` floor. On failure, stages an admin notice and returns
	 * false.
	 *
	 * `plugins_loaded` is the earliest the check is reliable; at include time the host may simply
	 * not have loaded yet.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	private function meets_wc_requirements(): bool {
		if ( ! \class_exists( 'WooCommerce' ) || ! \defined( 'WC_VERSION' ) ) {
			add_action(
				'all_admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					$notice = wp_sprintf(
						/* translators: %s: Plugin name */
						__( '%s requires WooCommerce to be installed and active, so the plugin stayed off.', 'a8csp-ai-canvas-webmcp' ),
						a8csp_ai_webmcp_get_plugin_name()
					);

					wp_admin_notice( esc_html( $notice ), array( 'type' => 'error' ) );
				}
			);

			return false;
		}

		// The header-declared version floor is part of the same gate — everything behind it
		// assumes the host. A header that declares no floor imposes none.
		$minimum_wc_version = a8csp_ai_webmcp_get_plugin_metadata( 'WC requires at least' );
		if ( null !== $minimum_wc_version && '' !== $minimum_wc_version && \version_compare( (string) \constant( 'WC_VERSION' ), $minimum_wc_version, '<' ) ) {
			add_action(
				'all_admin_notices',
				static function (): void {
					// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability for store managers and administrators.
					if ( ! current_user_can( 'manage_woocommerce' ) ) {
						return;
					}

					$notice = wp_sprintf(
						/* translators: 1: Plugin name, 2: Minimum WooCommerce version, 3: Running WooCommerce version */
						__( '%1$s requires WooCommerce %2$s or newer; version %3$s is running, so the plugin stayed off.', 'a8csp-ai-canvas-webmcp' ),
						a8csp_ai_webmcp_get_plugin_name(),
						(string) a8csp_ai_webmcp_get_plugin_metadata( 'WC requires at least' ),
						(string) \constant( 'WC_VERSION' )
					);

					wp_admin_notice( esc_html( $notice ), array( 'type' => 'error' ) );
				}
			);

			return false;
		}

		return true;
	}

	// endregion
}
