<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Mcp;

use A8C\SpecialProjects\AICanvas\PageAccess\Flag;
use A8C\SpecialProjects\AICanvas\Templates;

\defined( 'ABSPATH' ) || exit;

/**
 * The page operations behind the MCP tools: create a page, flagged as open to AI Canvas, and read,
 * change, or trash a page only while it carries that flag. The site owner can switch the flag on
 * any page from the page editor.
 *
 * The abilities' permission callback is the coarse gate. Core replaces any error a permission
 * callback returns with a generic one, so the checks whose failure the client has to understand —
 * the flag, the per-page capability, `unfiltered_html` — run here and return their own errors.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Pages {
	// region METHODS

	/**
	 * Whether the current user may use the page tools at all.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	public function can_use(): bool {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Lists the flagged pages the current user can edit, most recently modified first.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array{pages: list<array<string, int|string>>}
	 */
	public function list(): array {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The flag is the only thing that identifies these pages.
				'meta_key'       => Flag::META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				'meta_value'     => '1',
			)
		);

		$pages = \array_filter( $pages, static fn ( \WP_Post $page ): bool => current_user_can( 'edit_post', $page->ID ) );

		return array( 'pages' => \array_map( $this->describe( ... ), \array_values( $pages ) ) );
	}

	/**
	 * Returns a flagged page with its content.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{id: int} $input The tool input.
	 *
	 * @return  array<string, int|string>|\WP_Error
	 */
	public function get( array $input ): array|\WP_Error {
		$page = $this->resolve( $input['id'], 'edit_post' );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		return $this->describe( $page ) + array( 'content' => $page->post_content );
	}

	/**
	 * Creates a page and flags it as open to AI Canvas.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{title: string, content: string, template?: string, status?: string} $input The tool input.
	 *
	 * @return  array<string, int|string>|\WP_Error
	 */
	public function create( array $input ): array|\WP_Error {
		$status = $input['status'] ?? 'draft';

		$error = $this->check_write( $input['content'], $status );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// wp_insert_post() expects slashed data; unslashed, it eats the backslashes in the script.
		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => $status,
					'post_title'   => sanitize_text_field( $input['title'] ),
					'post_content' => $input['content'],
					'meta_input'   => array(
						Flag::META_KEY      => '1',
						'_wp_page_template' => Templates\Component::SLUGS[ $input['template'] ?? 'header-footer' ],
					),
				)
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		$page = $this->resolve( $page_id, 'edit_post' );

		return is_wp_error( $page ) ? $page : $this->describe( $page );
	}

	/**
	 * Changes a flagged page's content, title, status, or template.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{id: int, title?: string, content?: string, template?: string, status?: string} $input The tool input.
	 *
	 * @return  array<string, int|string>|\WP_Error
	 */
	public function update( array $input ): array|\WP_Error {
		$page = $this->resolve( $input['id'], 'edit_post' );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		// Any update re-saves the stored content, so every update is a write of it.
		$error = $this->check_write( $input['content'] ?? null, $input['status'] ?? null );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$changes = array();
		if ( isset( $input['content'] ) ) {
			$changes['post_content'] = $input['content'];
		}
		if ( isset( $input['title'] ) ) {
			$changes['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['status'] ) ) {
			$changes['post_status'] = $input['status'];
		}

		if ( array() === $changes && ! isset( $input['template'] ) ) {
			return new \WP_Error( 'a8csp_ai_canvas_nothing_to_update', __( 'Nothing to update: pass content, title, status, or template.', 'a8csp-ai-canvas' ) );
		}

		if ( array() !== $changes ) {
			$result = wp_update_post( wp_slash( array( 'ID' => $page->ID ) + $changes ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $input['template'] ) ) {
			update_post_meta( $page->ID, '_wp_page_template', Templates\Component::SLUGS[ $input['template'] ] );
		}

		$page = $this->resolve( $page->ID, 'edit_post' );

		return is_wp_error( $page ) ? $page : $this->describe( $page );
	}

	/**
	 * Moves a flagged page to the Trash, where the site owner can restore it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{id: int} $input The tool input.
	 *
	 * @return  array{id: int, title: string, status: string}|\WP_Error
	 */
	public function delete( array $input ): array|\WP_Error {
		$page = $this->resolve( $input['id'], 'delete_post' );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		if ( ! wp_trash_post( $page->ID ) instanceof \WP_Post ) {
			return new \WP_Error( 'a8csp_ai_canvas_delete_failed', __( 'WordPress could not move the page to the Trash.', 'a8csp-ai-canvas' ) );
		}

		return array(
			'id'     => $page->ID,
			'title'  => $page->post_title,
			'status' => 'trash',
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Resolves a page the tools may act on: an existing, untrashed page that carries the flag and
	 * that the current user holds the given capability for.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int    $page_id    The page ID.
	 * @param   string $capability The meta capability the action needs: `edit_post` or `delete_post`.
	 *
	 * @return  \WP_Post|\WP_Error
	 */
	private function resolve( int $page_id, string $capability ): \WP_Post|\WP_Error {
		$page = get_post( $page_id );
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			return new \WP_Error(
				'a8csp_ai_canvas_page_not_found',
				/* translators: %d: Page ID */
				\sprintf( __( 'No page with ID %d. Use list-pages to find the pages you can work on.', 'a8csp-ai-canvas' ), $page_id )
			);
		}

		if ( ! Flag::is_open( $page->ID ) ) {
			return new \WP_Error(
				'a8csp_ai_canvas_page_not_open',
				/* translators: %d: Page ID */
				\sprintf( __( 'Page %d is not open to AI Canvas, so these tools cannot read, change, or delete it. The site owner can open it from the page editor.', 'a8csp-ai-canvas' ), $page_id )
			);
		}

		if ( ! current_user_can( $capability, $page->ID ) ) {
			return new \WP_Error(
				'a8csp_ai_canvas_forbidden',
				/* translators: %d: Page ID */
				\sprintf( __( 'The connected user is not allowed to do that to page %d.', 'a8csp-ai-canvas' ), $page_id )
			);
		}

		return $page;
	}

	/**
	 * Checks what a create or update needs beyond the page itself: `unfiltered_html`, the content's
	 * shape when content is being written (one Custom HTML block, aligned to the full width), and the
	 * capability to publish when the status asks for it.
	 *
	 * Without `unfiltered_html`, WordPress strips the style and script elements on save and stores
	 * their contents as text, so the write is refused instead of saving a broken page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string|null $content The content being written, or null when the content is unchanged.
	 * @param   string|null $status  The status being set, or null when the status is unchanged.
	 *
	 * @return  true|\WP_Error
	 */
	private function check_write( ?string $content, ?string $status ): true|\WP_Error {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return new \WP_Error(
				'a8csp_ai_canvas_unfiltered_html_required',
				__( 'The connected user is not allowed to save scripts and styles, so WordPress would strip them from the page. On a multisite network only network administrators can; otherwise the site disallows it. The site owner or host has to change that.', 'a8csp-ai-canvas' )
			);
		}

		if ( null !== $content ) {
			$blocks = \array_values(
				\array_filter(
					parse_blocks( $content ),
					static fn ( array $block ): bool => null !== $block['blockName'] || '' !== \trim( $block['innerHTML'] )
				)
			);
			if ( 1 !== \count( $blocks ) || 'core/html' !== $blocks[0]['blockName'] || 'full' !== ( $blocks[0]['attrs']['align'] ?? null ) ) {
				return new \WP_Error(
					'a8csp_ai_canvas_invalid_content',
					__( 'The content must be exactly one Custom HTML block aligned to the full width: <!-- wp:html {"align":"full"} --> … <!-- /wp:html -->, with nothing outside it. Call get-instructions for the format.', 'a8csp-ai-canvas' )
				);
			}
		}

		if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
			return new \WP_Error( 'a8csp_ai_canvas_cannot_publish', __( 'The connected user is not allowed to publish pages. Save the page as a draft instead.', 'a8csp-ai-canvas' ) );
		}

		return true;
	}

	/**
	 * Returns the page summary every page tool responds with.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   \WP_Post $page The page.
	 *
	 * @return  array<string, int|string>
	 */
	private function describe( \WP_Post $page ): array {
		$template = \array_search( get_page_template_slug( $page ), Templates\Component::SLUGS, true );

		return array(
			'id'        => $page->ID,
			'title'     => $page->post_title,
			'status'    => $page->post_status,
			'template'  => false === $template ? 'other' : $template,
			'link'      => (string) ( 'publish' === $page->post_status ? get_permalink( $page ) : get_preview_post_link( $page ) ),
			'edit_link' => (string) get_edit_post_link( $page, 'raw' ),
		);
	}

	// endregion
}
