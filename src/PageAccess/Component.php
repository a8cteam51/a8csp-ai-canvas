<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\PageAccess;

use A8C\SpecialProjects\AICanvas\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the Page Access feature: an editor's control over which pages are open to the MCP
 * tools. The tools set the flag on the pages they create; this feature shows it and lets it be
 * switched, per page, in two places: the page editor's sidebar, and an AI Canvas column on the
 * Pages list. Both are for the Editor role and above, and so is changing the flag behind them.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Component extends AbstractComponent {
	// region FIELDS AND CONSTANTS

	/**
	 * The capability the switches need: the one that separates an Editor from the roles below.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	private const string CAPABILITY = 'edit_others_pages';

	/**
	 * The Pages list column's key.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	private const string COLUMN = 'a8csp-ai-canvas';

	/**
	 * The REST namespace of the route the switches read and save the flag through.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string ROUTE_NAMESPACE = 'a8csp-ai-canvas/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_switch' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_switches' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the route both switches read and save the flag through, for an editor who can
	 * edit the page.
	 *
	 * The flag is deliberately not registered with core's page route. That route re-saves the
	 * whole page, which moves its modified date and, for a user without `unfiltered_html`, strips
	 * the scripts out of its content. And the editor re-sends all of a page's meta whenever any of
	 * it changes, so a key some users may not write gets their whole save refused. This route
	 * touches the flag and nothing else.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_route(): void {
		$can_switch = static fn ( \WP_REST_Request $request ): bool => current_user_can( self::CAPABILITY ) && current_user_can( 'edit_post', (int) $request['id'] );

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/pages/(?P<id>\d+)/access',
			array(
				'args' => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_access' ),
					'permission_callback' => $can_switch,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_access' ),
					'permission_callback' => $can_switch,
					'args'                => array(
						'open' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Reports whether a page is open to the MCP tools.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   \WP_REST_Request<array{id: int}> $request The request.
	 *
	 * @return  \WP_REST_Response|\WP_Error
	 */
	public function get_access( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page_id = (int) $request['id'];
		if ( 'page' !== get_post_type( $page_id ) ) {
			return self::page_not_found();
		}

		return new \WP_REST_Response( array( 'open' => Flag::is_open( $page_id ) ) );
	}

	/**
	 * Opens a page to the MCP tools, or closes it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   \WP_REST_Request<array{id: int, open: bool}> $request The request.
	 *
	 * @return  \WP_REST_Response|\WP_Error
	 */
	public function set_access( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page_id = (int) $request['id'];
		if ( 'page' !== get_post_type( $page_id ) ) {
			return self::page_not_found();
		}

		Flag::set( $page_id, (bool) $request['open'] );

		return new \WP_REST_Response( array( 'open' => Flag::is_open( $page_id ) ) );
	}

	/**
	 * Enqueues the sidebar switch's script for an editor, in the page editor and the site editor,
	 * the two editors a page is edited in. The script itself shows the switch for pages only.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_editor_switch(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$is_page_editor = 'post' === $screen->base && 'page' === $screen->post_type;
		if ( $is_page_editor || 'site-editor' === $screen->base ) {
			$this->enqueue_script( 'page-editor' );
		}
	}

	/**
	 * Enqueues the Pages list switches' script for an editor, and the component styles a switch
	 * is drawn with.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $hook_suffix The current admin page's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_list_switches( string $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix || 'page' !== get_current_screen()?->post_type || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$this->enqueue_script( 'pages-list' );
		wp_enqueue_style( 'wp-components' );
		// The column holds a switch and nothing else; left to the table, it takes a share of the width.
		wp_add_inline_style( 'wp-components', '.fixed .column-' . self::COLUMN . '{width:7em}' );
	}

	/**
	 * Adds the AI Canvas column to an editor's Pages list, ahead of the date.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, string> $columns The list's columns, as key and heading.
	 *
	 * @return  array<string, string>
	 */
	public function add_column( array $columns ): array {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return $columns;
		}

		$date = \array_search( 'date', \array_keys( $columns ), true );
		$at   = false === $date ? \count( $columns ) : $date;

		return \array_slice( $columns, 0, $at, true )
			+ array( self::COLUMN => __( 'AI Canvas', 'a8csp-ai-canvas' ) )
			+ \array_slice( $columns, $at, null, true );
	}

	/**
	 * Renders a page's AI Canvas cell: whether the page is open to the MCP tools. For a page the
	 * current user can edit, the cell carries what the list script needs to draw a switch in
	 * place of the text. A trashed page gets the text alone: the tools treat it as gone.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $column  The column being rendered.
	 * @param   int    $page_id The page ID.
	 *
	 * @return  void
	 */
	public function render_column( string $column, int $page_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$is_open = Flag::is_open( $page_id );
		$state   = $is_open ? __( 'On', 'a8csp-ai-canvas' ) : __( 'Off', 'a8csp-ai-canvas' );

		if ( 'trash' === get_post_status( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			echo esc_html( $state );
			return;
		}

		\printf(
			'<span class="a8csp-ai-canvas-access" data-page-id="%1$d" data-open="%2$s" data-title="%3$s">%4$s</span>',
			absint( $page_id ),
			$is_open ? '1' : '0',
			esc_attr( get_the_title( $page_id ) ),
			esc_html( $state )
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the error for an ID that is not a page's.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  \WP_Error
	 */
	private static function page_not_found(): \WP_Error {
		return new \WP_Error( 'a8csp_ai_canvas_page_not_found', __( 'There is no page with that ID.', 'a8csp-ai-canvas' ), array( 'status' => 404 ) );
	}

	/**
	 * Enqueues one of the feature's built scripts, with its translations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $name The script's file name under `assets/js/build`, without its extension.
	 *
	 * @return  void
	 */
	private function enqueue_script( string $name ): void {
		$asset_meta = a8csp_ai_canvas_get_asset_meta( "assets/js/build/$name.js" );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$handle = a8csp_ai_canvas_get_plugin_slug() . '-' . $name;

		wp_enqueue_script( $handle, \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . "assets/js/build/$name.js", $asset_meta['dependencies'], $asset_meta['version'], true );
		wp_set_script_translations( $handle, 'a8csp-ai-canvas' );
	}

	// endregion
}
