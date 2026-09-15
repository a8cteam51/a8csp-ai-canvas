<?php
/**
 * The MCP tool surface, registered as core Abilities (WP 6.9+): schemas,
 * descriptions, and permission gates. Execute callbacks live in
 * AI_Canvas_Pages and AI_Canvas_Media.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Abilities {

	const ABILITIES = array(
		'ai-canvas/get-guidelines',
		'ai-canvas/site-info',
		'ai-canvas/find-pages',
		'ai-canvas/get-page',
		'ai-canvas/create-page',
		'ai-canvas/update-page',
		'ai-canvas/list-revisions',
		'ai-canvas/restore-revision',
		'ai-canvas/trash-page',
		'ai-canvas/check-page',
		'ai-canvas/list-media',
		'ai-canvas/upload-media',
	);

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			'ai-canvas',
			array(
				'label'       => __( 'AI Canvas', 'ai-canvas' ),
				'description' => __( 'Build and edit pages and posts as a single full-width Custom HTML block, and manage media.', 'ai-canvas' ),
			)
		);
	}

	// --- Permission gates --------------------------------------------------.

	/**
	 * Floor for every tool and for the MCP transport itself.
	 */
	public static function can_use_server(): bool {
		return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
	}

	public static function can_edit_target( $input = array() ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return self::can_use_server() && $id > 0 && current_user_can( 'edit_post', $id );
	}

	public static function can_delete_target( $input = array() ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return self::can_use_server() && $id > 0 && current_user_can( 'delete_post', $id );
	}

	public static function can_publish_target( $input = array() ): bool {
		if ( ! self::can_edit_target( $input ) ) {
			return false;
		}
		if ( in_array( $input['status'] ?? '', array( 'publish', 'private' ), true ) ) {
			$type = get_post_type_object( (string) get_post_type( (int) $input['id'] ) );
			return $type && current_user_can( $type->cap->publish_posts );
		}
		return true;
	}

	public static function can_create( $input = array() ): bool {
		$type = get_post_type_object( $input['post_type'] ?? 'page' );
		if ( ! $type || ! self::can_use_server() || ! current_user_can( $type->cap->edit_posts ) ) {
			return false;
		}
		if ( 'draft' !== ( $input['status'] ?? 'publish' ) && ! current_user_can( $type->cap->publish_posts ) ) {
			return false;
		}
		return true;
	}

	public static function can_upload(): bool {
		return self::can_use_server() && current_user_can( 'upload_files' );
	}

	// --- Registration ------------------------------------------------------.

	/**
	 * Meta shared by every ability: exposed to MCP, hidden from the abilities REST channel.
	 */
	private static function meta( array $annotations = array() ): array {
		return array(
			'mcp'          => array( 'public' => true ),
			'show_in_rest' => false,
			'annotations'  => $annotations,
		);
	}

	private static function readonly(): array {
		return self::meta( array( 'readonly' => true ) );
	}

	private static function id_schema( array $extra = array(), array $required = array( 'id' ) ): array {
		return array(
			'type'       => 'object',
			'required'   => $required,
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer', 'description' => 'Page or post id.' ) ),
				$extra
			),
		);
	}

	private static function summary_plus( array $extra ): array {
		$schema                    = AI_Canvas_Content::summary_schema();
		$schema['properties']     += $extra;
		return $schema;
	}

	private static function warnings_schema(): array {
		return array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => 'Non-fatal problems with the content; fix them in the next update.',
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'ai-canvas/get-guidelines',
			array(
				'label'               => __( 'Get guidelines', 'ai-canvas' ),
				'description'         => __( 'The full authoring contract for pages on this site: the exact Custom HTML block shape, CSS scoping, JS rules, editor behaviour, workflow, performance, and how to talk to the site owner. Call once per session before the first create-page or update-page.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => array( 'type' => 'object', 'properties' => (object) array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'guidelines'         => array( 'type' => 'string' ),
						'guidelines_version' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_use_server' ),
				'execute_callback'    => fn() => array(
					'guidelines'         => AI_Canvas_Guidelines::text(),
					'guidelines_version' => AI_Canvas_Guidelines::VERSION,
				),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/site-info',
			array(
				'label'               => __( 'Site info', 'ai-canvas' ),
				'description'         => __( 'Site name, theme, the connection user and its capabilities (including whether it can publish scripts and styles), template availability, and upload limit. Call once per session as preflight.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => array( 'type' => 'object', 'properties' => (object) array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site_name'                    => array( 'type' => 'string' ),
						'home_url'                     => array( 'type' => 'string' ),
						'theme'                        => array( 'type' => 'object' ),
						'user'                         => array( 'type' => 'object' ),
						'capabilities'                 => array( 'type' => 'object' ),
						'can_write_scripts_and_styles' => array( 'type' => 'boolean' ),
						'templates'                    => array( 'type' => 'object' ),
						'alignfull_wrapper'            => array( 'type' => 'string', 'enum' => array( 'ai-canvas', 'jvhe' ) ),
						'max_upload_bytes'             => array( 'type' => 'integer' ),
						'plugin_version'               => array( 'type' => 'string' ),
						'guidelines_version'           => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_use_server' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'site_info' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/find-pages',
			array(
				'label'               => __( 'Find pages', 'ai-canvas' ),
				'description'         => __( 'Search pages or posts the connection user can edit, newest-modified first. Use before creating anything the user may already have.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'enum' => array( 'page', 'post' ), 'default' => 'page' ),
						'search'    => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'any', 'publish', 'draft', 'private', 'trash' ), 'default' => 'any' ),
						'limit'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array( 'type' => 'array', 'items' => AI_Canvas_Content::summary_schema() ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_use_server' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'find' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/get-page',
			array(
				'label'               => __( 'Get page', 'ai-canvas' ),
				'description'         => __( 'Fetch one page or post with its raw block content. Read before every update-page: the owner may have edited it, and update-page replaces the whole block.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema(),
				'output_schema'       => self::summary_plus(
					array(
						'content'            => array( 'type' => 'string' ),
						'bytes'              => array( 'type' => 'integer' ),
						'warnings'           => self::warnings_schema(),
						'revision_count'     => array( 'type' => 'integer' ),
						'latest_revision_id' => array( 'type' => array( 'integer', 'null' ) ),
					)
				),
				'permission_callback' => array( __CLASS__, 'can_edit_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'get' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/create-page',
			array(
				'label'               => __( 'Create page', 'ai-canvas' ),
				'description'         => __( 'Create a page or post whose body is one full-width Custom HTML block. Pages publish immediately by default and are unlinked from menus; for posts ask whether to publish or save a draft. ', 'ai-canvas' ) . AI_Canvas_Guidelines::pointer(),
				'category'            => 'ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'title', 'content' ),
					'properties' => array(
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string', 'description' => 'The complete Custom HTML block, delimiters included.' ),
						'post_type' => array( 'type' => 'string', 'enum' => array( 'page', 'post' ), 'default' => 'page' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private' ), 'default' => 'publish' ),
						'slug'      => array( 'type' => 'string' ),
						'template'  => array(
							'type'        => 'string',
							'enum'        => array( 'framed', 'blank', 'theme' ),
							'description' => 'framed = site header and footer around the block, no page title (default for pages); blank = only the block; theme = the theme\'s normal layout (default for posts).',
						),
						'parent'    => array( 'type' => 'integer' ),
						'excerpt'   => array( 'type' => 'string', 'description' => 'Plain-text summary; set it for posts or the auto-excerpt shows raw style/script text.' ),
					),
				),
				'output_schema'       => self::summary_plus( array( 'warnings' => self::warnings_schema() ) ),
				'permission_callback' => array( __CLASS__, 'can_create' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'create' ),
				'meta'                => self::meta(),
			)
		);

		wp_register_ability(
			'ai-canvas/update-page',
			array(
				'label'               => __( 'Update page', 'ai-canvas' ),
				'description'         => __( 'Replace the whole block and/or change title, status, slug, template, parent, or excerpt. WordPress records the previous version as a revision. Call get-page first. ', 'ai-canvas' ) . AI_Canvas_Guidelines::pointer(),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema(
					array(
						'content'  => array( 'type' => 'string', 'description' => 'The complete Custom HTML block, delimiters included.' ),
						'title'    => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'private' ) ),
						'slug'     => array( 'type' => 'string' ),
						'template' => array( 'type' => 'string', 'enum' => array( 'framed', 'blank', 'theme' ) ),
						'parent'   => array( 'type' => 'integer' ),
						'excerpt'  => array( 'type' => 'string' ),
					)
				),
				'output_schema'       => self::summary_plus(
					array(
						'revision_id' => array( 'type' => array( 'integer', 'null' ), 'description' => 'The revision that now holds the previous content.' ),
						'changed'     => array( 'type' => 'boolean' ),
						'warnings'    => self::warnings_schema(),
					)
				),
				'permission_callback' => array( __CLASS__, 'can_publish_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'update' ),
				'meta'                => self::meta(),
			)
		);

		wp_register_ability(
			'ai-canvas/list-revisions',
			array(
				'label'               => __( 'List revisions', 'ai-canvas' ),
				'description'         => __( 'Revisions of a page or post, newest first. The newest normally matches the current content, so "undo" is the one below it. A page has no revisions until its first update.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema( array( 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ) ) ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'revisions' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'revision_id'     => array( 'type' => 'integer' ),
									'modified_gmt'    => array( 'type' => 'string' ),
									'author'          => array( 'type' => 'string' ),
									'bytes'           => array( 'type' => 'integer' ),
									'matches_current' => array( 'type' => 'boolean' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_edit_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'list_revisions' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/restore-revision',
			array(
				'label'               => __( 'Restore revision', 'ai-canvas' ),
				'description'         => __( 'Put a revision\'s content back (title and excerpt too). The restore is itself recorded as a new revision, so it can be undone.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema( array( 'revision_id' => array( 'type' => 'integer' ) ), array( 'id', 'revision_id' ) ),
				'output_schema'       => self::summary_plus(
					array(
						'restored_revision_id' => array( 'type' => 'integer' ),
						'revision_id'          => array( 'type' => array( 'integer', 'null' ) ),
					)
				),
				'permission_callback' => array( __CLASS__, 'can_edit_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'restore_revision' ),
				'meta'                => self::meta(),
			)
		);

		wp_register_ability(
			'ai-canvas/trash-page',
			array(
				'label'               => __( 'Trash page', 'ai-canvas' ),
				'description'         => __( 'Move a page or post to the Trash, where the owner can restore it in wp-admin.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
						'title'  => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_delete_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'trash' ),
				'meta'                => self::meta( array( 'destructive' => true ) ),
			)
		);

		wp_register_ability(
			'ai-canvas/check-page',
			array(
				'label'               => __( 'Check page', 'ai-canvas' ),
				'description'         => __( 'Fetch the public page and report whether it is public, full-width, and whether the style and script survived (script_was_escaped means the user cannot publish scripts; script_ampersand_rewritten means a bare "<" broke the script). Run after every create or update.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => self::id_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'link'                       => array( 'type' => 'string' ),
						'status'                     => array( 'type' => 'string' ),
						'http'                       => array( 'type' => array( 'integer', 'null' ) ),
						'public'                     => array( 'type' => array( 'boolean', 'null' ) ),
						'fetched_via'                => array( 'type' => 'string', 'enum' => array( 'http', 'in-process' ) ),
						'error'                      => array( 'type' => array( 'string', 'null' ) ),
						'alignfull_wrapper'          => array( 'type' => 'boolean' ),
						'style_tag_present'          => array( 'type' => 'boolean' ),
						'script_tag_present'         => array( 'type' => 'boolean' ),
						'script_was_escaped'         => array( 'type' => 'boolean' ),
						'script_ampersand_rewritten' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_edit_target' ),
				'execute_callback'    => array( 'AI_Canvas_Pages', 'check' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/list-media',
			array(
				'label'               => __( 'List media', 'ai-canvas' ),
				'description'         => __( 'Search the Media Library and get attachment URLs to reference from the block. Image results include pixel dimensions and the generated smaller sizes; reference the smallest size that covers the display area, and mirror its width/height in the HTML.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array( 'type' => 'string' ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'media' => array( 'type' => 'array', 'items' => AI_Canvas_Media::schema() ),
					),
				),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
				'execute_callback'    => array( 'AI_Canvas_Media', 'search' ),
				'meta'                => self::readonly(),
			)
		);

		wp_register_ability(
			'ai-canvas/upload-media',
			array(
				'label'               => __( 'Upload media', 'ai-canvas' ),
				'description'         => __( 'Add a file to the Media Library from a URL or base64 data, and get back the attachment URL to reference from the block. For images, the result includes pixel dimensions and the generated smaller sizes.', 'ai-canvas' ),
				'category'            => 'ai-canvas',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'url'      => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Provide either url or base64.' ),
						'base64'   => array( 'type' => 'string', 'description' => 'Requires filename.' ),
						'filename' => array( 'type' => 'string' ),
						'title'    => array( 'type' => 'string' ),
						'alt'      => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => AI_Canvas_Media::schema(),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
				'execute_callback'    => array( 'AI_Canvas_Media', 'upload' ),
				'meta'                => self::meta(),
			)
		);
	}
}
