<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Connector;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\Mcp;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the Connector feature: adds an AI Canvas card to the Settings → Connectors screen. The
 * card holds what an MCP client needs to connect: the server URL, and a generator that turns a
 * chosen user's application password into an `Authorization` header value.
 *
 * Nothing is persisted. The header is computed in the browser, so the application password is
 * never sent to the site. The screen is core's and needs `manage_options`.
 *
 * The card's script registers itself through the `__experimental` exports of core's
 * `@wordpress/connectors` script module; if a later WordPress renames them, the card stops
 * rendering and nothing else is affected.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Component extends AbstractComponent {
	// region FIELDS AND CONSTANTS

	/**
	 * The connector's ID in core's registry, which the card's script registers its UI under.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string CONNECTOR_ID = 'a8csp-ai-canvas';

	// endregion

	// region METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'wp_connectors_init', array( $this, 'register_connector' ) );
		add_action( 'options-connectors-wp-admin_init', array( $this, 'enqueue_card' ) );
		add_filter( 'options-connectors-wp-admin_boot_dependencies', array( $this, 'require_connectors_module' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the connector. It authenticates nothing itself, so core gives it no settings UI:
	 * the card's script supplies the whole card. The logo is the "O" of the Automattic wordmark.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   \WP_Connector_Registry $registry Core's connector registry.
	 *
	 * @return  void
	 */
	public function register_connector( \WP_Connector_Registry $registry ): void {
		$registry->register(
			self::CONNECTOR_ID,
			array(
				// The product's name, which is not translated.
				'name'           => 'AI Canvas',
				'description'    => __( 'Let Claude Design, or another MCP client, build pages on this site.', 'a8csp-ai-canvas' ),
				'logo_url'       => \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/images/connector-logo.png',
				'type'           => 'mcp_server',
				'authentication' => array( 'method' => 'none' ),
			)
		);
	}

	/**
	 * Enqueues the card's script on the Connectors screen, with the data the card renders.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_card(): void {
		$asset_meta = a8csp_ai_canvas_get_asset_meta( 'assets/js/build/connector.js' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$handle = a8csp_ai_canvas_get_plugin_slug() . '-connector';

		wp_enqueue_script( $handle, \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/js/build/connector.js', $asset_meta['dependencies'], $asset_meta['version'], true );
		wp_set_script_translations( $handle, 'a8csp-ai-canvas' );
		wp_add_inline_script( $handle, 'window.a8cspAiCanvasConnector = ' . wp_json_encode( $this->get_card_data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}

	/**
	 * Keeps core's `@wordpress/connectors` script module in the screen's import map, which is
	 * where the card's script imports the registration API from.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int, array{import: string, id: string}> $dependencies The screen's script-module dependencies.
	 *
	 * @return  array<int, array{import: string, id: string}>
	 */
	public function require_connectors_module( array $dependencies ): array {
		$dependencies[] = array(
			'import' => 'dynamic',
			'id'     => '@wordpress/connectors',
		);

		return $dependencies;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the data the card renders: the MCP server URL, and the users a client can connect as.
	 *
	 * The users are those who can edit pages, the capability every MCP tool needs. Each carries
	 * the link to where their application passwords are created, when the current user may go
	 * there.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array{serverUrl: string, currentUser: string, users: list<array{login: string, name: string, passwordsUrl: string}>}
	 */
	public function get_card_data(): array {
		$users = get_users(
			array(
				'capability' => 'edit_pages',
				'orderby'    => 'display_name',
			)
		);

		return array(
			'serverUrl'   => rest_url( Mcp\Component::ROUTE_NAMESPACE . '/' . Mcp\Component::ROUTE ),
			'currentUser' => wp_get_current_user()->user_login,
			'users'       => \array_map(
				static fn ( \WP_User $user ): array => array(
					'login'        => $user->user_login,
					'name'         => $user->display_name,
					'passwordsUrl' => current_user_can( 'edit_user', $user->ID ) ? get_edit_user_link( $user->ID ) . '#application-passwords-section' : '',
				),
				\array_values( $users )
			),
		);
	}

	// endregion
}
