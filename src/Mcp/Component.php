<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Mcp;

use A8C\SpecialProjects\AICanvas\ComponentInterface;
use A8C\SpecialProjects\AICanvas\Templates;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the MCP feature: registers the page and media tools as abilities and exposes them,
 * with the authoring instructions, on the plugin's own MCP server at
 * `/wp-json/a8csp-ai-canvas/mcp`.
 *
 * The server needs the MCP Adapter plugin, so the feature gates on it and none of it is
 * constructed when the adapter is absent.
 *
 * @since   1.0.0
 * @version 1.1.0
 */
final class Component implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The REST namespace and route the MCP server is served on.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string ROUTE_NAMESPACE = 'a8csp-ai-canvas';
	public const string ROUTE           = 'mcp';

	/**
	 * The abilities the MCP server exposes as tools.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<string>
	 */
	private const array ABILITIES = array(
		'a8csp-ai-canvas/get-instructions',
		'a8csp-ai-canvas/list-pages',
		'a8csp-ai-canvas/get-page',
		'a8csp-ai-canvas/create-page',
		'a8csp-ai-canvas/update-page',
		'a8csp-ai-canvas/delete-page',
		'a8csp-ai-canvas/list-media',
		'a8csp-ai-canvas/upload-media',
	);

	/**
	 * The page operations, wired in `initialize()`.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     Pages|null
	 */
	private ?Pages $pages = null;

	/**
	 * The Media Library operations, wired in `initialize()`.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     Media|null
	 */
	private ?Media $media = null;

	// endregion

	// region METHODS

	/**
	 * Returns true when the MCP Adapter plugin is loaded.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	#[\Override]
	public static function should_load(): bool {
		return \class_exists( \WP\MCP\Core\McpAdapter::class );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function initialize(): void {
		$this->pages = new Pages();
		$this->media = new Media();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'create_server' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the category the abilities belong to.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			'a8csp-ai-canvas',
			array(
				'label'       => __( 'AI Canvas', 'a8csp-ai-canvas' ),
				'description' => __( 'Build pages as a single Custom HTML block, and manage the media they use.', 'a8csp-ai-canvas' ),
			)
		);
	}

	/**
	 * Registers the abilities. They are reachable through this plugin's MCP server only: none is
	 * shown in the abilities REST API or flagged public for the adapter's default server.
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 *
	 * @throws  \LogicException If the abilities register before initialization.
	 *
	 * @return  void
	 */
	public function register_abilities(): void {
		$pages = $this->pages ?? throw new \LogicException( 'MCP abilities registered before initialization.' );
		$media = $this->media ?? throw new \LogicException( 'MCP abilities registered before initialization.' );

		$template_names = \array_keys( Templates\Component::SLUGS );
		$page_schema    = array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'template'  => array(
					'type'        => 'string',
					'description' => 'header-footer, content-only, or other when the site owner chose a different template in the editor.',
				),
				'link'      => array(
					'type'        => 'string',
					'description' => 'Where to view the page. A preview that needs a wp-admin login until the page is published.',
				),
				'edit_link' => array(
					'type'        => 'string',
					'description' => 'The page in the WordPress editor.',
				),
			),
		);
		$input_fields   = array(
			'id'       => array(
				'type'        => 'integer',
				'description' => 'The page ID, from create-page or list-pages.',
			),
			'title'    => array(
				'type'        => 'string',
				'description' => 'The page title. Neither template prints it; it names the page in wp-admin and the browser tab.',
			),
			'content'  => array(
				'type'        => 'string',
				'description' => 'The complete page body: exactly one Custom HTML block, aligned to the full width.',
			),
			'template' => array(
				'type'        => 'string',
				'enum'        => $template_names,
				'description' => 'header-footer: the site header and footer around the content. content-only: nothing but the content.',
			),
			'status'   => array(
				'type'        => 'string',
				'enum'        => array( 'draft', 'publish' ),
				'description' => 'draft keeps the page private; publish makes it live.',
			),
		);

		wp_register_ability(
			'a8csp-ai-canvas/get-instructions',
			array(
				'label'               => __( 'Get instructions', 'a8csp-ai-canvas' ),
				'description'         => __( 'Returns the rules for writing page content for this site: the Custom HTML block format, the CSS and JavaScript constraints, the templates, and the workflow. Call it once per conversation before the first create-page or update-page.', 'a8csp-ai-canvas' ),
				'category'            => 'a8csp-ai-canvas',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'instructions' => array( 'type' => 'string' ) ),
				),
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => static fn (): array => array( 'instructions' => self::guide() ),
				'meta'                => self::meta( array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/list-pages',
			array(
				'label'               => __( 'List pages', 'a8csp-ai-canvas' ),
				'description'         => __( 'Lists the pages open to these tools, most recently modified first: the ones they created, and any the site owner has opened from the page editor. These are the only pages the other tools can read, change, or delete.', 'a8csp-ai-canvas' ),
				'category'            => 'a8csp-ai-canvas',
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'pages' => array(
							'type'  => 'array',
							'items' => $page_schema,
						),
					),
				),
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => $pages->list( ... ),
				'meta'                => self::meta( array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/get-page',
			array(
				'label'               => __( 'Get page', 'a8csp-ai-canvas' ),
				'description'         => __( 'Returns a page open to these tools, with its current content. Call it before update-page: the site owner may have edited the page.', 'a8csp-ai-canvas' ),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array( 'id' => $input_fields['id'] ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $page_schema['properties'] + array( 'content' => array( 'type' => 'string' ) ),
				),
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => $pages->get( ... ),
				'meta'                => self::meta( array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/create-page',
			array(
				'label'               => __( 'Create page', 'a8csp-ai-canvas' ),
				'description'         => __( 'Creates a page on this site from a single Custom HTML block and returns its ID and links. The page is a draft unless status is publish.', 'a8csp-ai-canvas' ) . ' ' . Instructions::approval() . ' ' . Instructions::pointer(),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'title', 'content' ),
					'properties' => array(
						'title'    => $input_fields['title'],
						'content'  => $input_fields['content'],
						'template' => $input_fields['template'] + array( 'default' => 'header-footer' ),
						'status'   => $input_fields['status'] + array( 'default' => 'draft' ),
					),
				),
				'output_schema'       => $page_schema,
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => $pages->create( ... ),
				'meta'                => self::meta( array( 'destructive' => false ) ),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/update-page',
			array(
				'label'               => __( 'Update page', 'a8csp-ai-canvas' ),
				'description'         => __( 'Changes a page open to these tools: its content, title, status, or template. Content replaces the whole page body, so send the complete block. WordPress keeps the previous version as a revision.', 'a8csp-ai-canvas' ) . ' ' . Instructions::approval() . ' ' . Instructions::pointer(),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => $input_fields,
				),
				'output_schema'       => $page_schema,
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => $pages->update( ... ),
				'meta'                => self::meta(),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/delete-page',
			array(
				'label'               => __( 'Delete page', 'a8csp-ai-canvas' ),
				'description'         => __( 'Moves a page open to these tools to the Trash. The site owner can restore it from wp-admin.', 'a8csp-ai-canvas' ),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array( 'id' => $input_fields['id'] ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'title'  => array( 'type' => 'string' ),
						'status' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => $pages->can_use( ... ),
				'execute_callback'    => $pages->delete( ... ),
				'meta'                => self::meta( array( 'destructive' => true ) ),
			)
		);

		$media_schema = array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'url'    => array(
					'type'        => 'string',
					'description' => 'The full-size file.',
				),
				'mime'   => array( 'type' => 'string' ),
				'title'  => array( 'type' => 'string' ),
				'alt'    => array( 'type' => 'string' ),
				'width'  => array( 'type' => array( 'integer', 'null' ) ),
				'height' => array( 'type' => array( 'integer', 'null' ) ),
				'sizes'  => array(
					'type'        => 'array',
					'description' => 'The smaller versions WordPress generated of an image.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'name'   => array( 'type' => 'string' ),
							'url'    => array( 'type' => 'string' ),
							'width'  => array( 'type' => 'integer' ),
							'height' => array( 'type' => 'integer' ),
						),
					),
				),
			),
		);

		wp_register_ability(
			'a8csp-ai-canvas/list-media',
			array(
				'label'               => __( 'List media', 'a8csp-ai-canvas' ),
				'description'         => __( 'Searches this site’s Media Library, most recent first, and returns each file’s URL. Images come with their pixel dimensions and generated smaller sizes: reference the smallest size that covers the display area, and copy its width and height into the markup.', 'a8csp-ai-canvas' ),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => 'Words to look for in the file’s title, caption, or description. Leave out to list the most recent files.',
						),
						'limit'  => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'media' => array(
							'type'  => 'array',
							'items' => $media_schema,
						),
					),
				),
				'permission_callback' => $media->can_use( ... ),
				'execute_callback'    => $media->list( ... ),
				'meta'                => self::meta( array( 'readonly' => true ) ),
			)
		);

		wp_register_ability(
			'a8csp-ai-canvas/upload-media',
			array(
				'label'               => __( 'Upload media', 'a8csp-ai-canvas' ),
				'description'         => __( 'Adds a file to this site’s Media Library, from a URL or from base64 data, and returns the URL to reference from a page. Images come back with their pixel dimensions and generated smaller sizes.', 'a8csp-ai-canvas' ) . ' ' . Instructions::local_files() . ' ' . Instructions::approval(),
				'category'            => 'a8csp-ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'url'      => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'A public URL to fetch the file from. Pass either url, or base64 with filename.',
						),
						'base64'   => array(
							'type'        => 'string',
							'description' => 'The file’s contents, base64-encoded. Needs filename. For a file of a few kilobytes, unless you can pass it without writing the characters out yourself.',
						),
						'filename' => array(
							'type'        => 'string',
							'description' => 'The name to store the file under, with its extension. Required with base64; optional with url.',
						),
						'title'    => array( 'type' => 'string' ),
						'alt'      => array(
							'type'        => 'string',
							'description' => 'The image’s alternative text.',
						),
					),
				),
				'output_schema'       => $media_schema,
				'permission_callback' => $media->can_use( ... ),
				'execute_callback'    => $media->upload( ... ),
				'meta'                => self::meta( array( 'destructive' => false ) ),
			)
		);
	}

	/**
	 * Creates the plugin's MCP server.
	 *
	 * The adapter serves the server description as the MCP `instructions`, on `initialize` and on
	 * sessionless discovery alike, so the authoring guide is passed as the description.
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 *
	 * @param   \WP\MCP\Core\McpAdapter $adapter The MCP Adapter instance.
	 *
	 * @return  void
	 */
	public function create_server( \WP\MCP\Core\McpAdapter $adapter ): void {
		$adapter->create_server(
			'a8csp-ai-canvas',
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			'AI Canvas',
			self::guide(),
			a8csp_ai_canvas_get_plugin_version(),
			array( \WP\MCP\Transport\HttpTransport::class ),
			null,
			null,
			self::ABILITIES,
			array(),
			array(),
			// Without a transport gate any logged-in user can list the tools and read the instructions.
			static fn (): bool => current_user_can( 'edit_pages' )
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the authoring guide for this site: the one text behind the server's instructions
	 * and the `get-instructions` tool, carrying the URL of the placeholder image this plugin ships.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @return  string
	 */
	private static function guide(): string {
		return Instructions::text( \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/images/placeholder.svg' );
	}

	/**
	 * Returns the meta every ability carries: hidden from the abilities REST API, with the given
	 * annotations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, bool> $annotations The ability's behavior annotations.
	 *
	 * @return  array<string, mixed>
	 */
	private static function meta( array $annotations = array() ): array {
		return array(
			'show_in_rest' => false,
			'annotations'  => $annotations,
		);
	}

	// endregion
}
