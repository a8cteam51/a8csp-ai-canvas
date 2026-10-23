<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Settings;

use A8C\SpecialProjects\AICanvasWebMCP\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the Settings feature: owns the plugin's two example settings surfaces — an option on
 * the General options page and a section in WooCommerce → Settings → Advanced.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @see footprint.php
 */
final class Component extends AbstractComponent {
	// region METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		// The Settings API is only loaded in the admin, so registration stages onto admin_init.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		add_filter( 'woocommerce_get_sections_advanced', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'provide_settings' ), 10, 2 );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the example option on the core General options page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		// Every persisted key is listed in the `footprint.php` manifest in the same change that
		// introduces the write.
		register_setting(
			'general',
			'a8csp_ai_webmcp_example_option',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section( 'a8csp_ai_webmcp_example_section', __( 'A8CSP AI Canvas WebMCP', 'a8csp-ai-canvas-webmcp' ), '__return_empty_string', 'general' );

		add_settings_field( 'a8csp_ai_webmcp_example_field', __( 'Example option', 'a8csp-ai-canvas-webmcp' ), array( $this, 'render_field' ), 'general', 'a8csp_ai_webmcp_example_section', array( 'label_for' => 'a8csp_ai_webmcp_example_option' ) );
	}

	/**
	 * Renders the example option's settings field.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function render_field(): void {
		\printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" />',
			esc_attr( 'a8csp_ai_webmcp_example_option' ),
			esc_attr( a8csp_ai_webmcp_get_example_option() )
		);
	}

	/**
	 * Enqueues the settings stylesheet on the General options page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $hook_suffix The current admin page's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_admin_styles( string $hook_suffix ): void {
		if ( 'options-general.php' !== $hook_suffix ) {
			return;
		}

		$asset_meta = a8csp_ai_webmcp_get_asset_meta( 'assets/css/build/settings.css' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$plugin_slug = a8csp_ai_webmcp_get_plugin_slug();
		wp_enqueue_style( "$plugin_slug-settings", \constant( 'A8CSP_AI_WEBMCP_DIR_URL' ) . 'assets/css/build/settings.css', $asset_meta['dependencies'], $asset_meta['version'] );
		wp_style_add_data( "$plugin_slug-settings", 'rtl', 'replace' );
	}

	/**
	 * Adds the plugin's section to the Advanced settings tab.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, string> $sections The registered Advanced settings sections.
	 *
	 * @return  array<string, string>
	 */
	public function add_section( array $sections ): array {
		$sections['a8csp_ai_webmcp'] = __( 'A8CSP AI Canvas WebMCP', 'a8csp-ai-canvas-webmcp' );

		return $sections;
	}

	/**
	 * Declares this section's WooCommerce settings rows.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int, array<string, mixed>> $settings        The settings rows for the current Advanced section.
	 * @param   string                           $current_section The current Advanced section slug.
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public function provide_settings( array $settings, string $current_section ): array {
		if ( 'a8csp_ai_webmcp' !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'A8CSP AI Canvas WebMCP', 'a8csp-ai-canvas-webmcp' ),
				'type'  => 'title',
				'id'    => 'a8csp_ai_webmcp_wc_example_section',
			),
			array(
				'title' => __( 'Example option', 'a8csp-ai-canvas-webmcp' ),
				'desc'  => __( 'A persisted example setting owned by the WooCommerce settings surface.', 'a8csp-ai-canvas-webmcp' ),
				// Listed in the `footprint.php` manifest; the line there goes with this surface when it is deleted.
				'id'    => 'a8csp_ai_webmcp_wc_example_option',
				'type'  => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'a8csp_ai_webmcp_wc_example_section',
			),
		);
	}

	// endregion
}
