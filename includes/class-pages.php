<?php
/**
 * Execute callbacks for the page tools: find, get, create, update, revisions,
 * restore, trash, check, site-info.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Pages {

	public static function site_info() {
		$theme = wp_get_theme();
		$user  = wp_get_current_user();
		$caps  = array();
		foreach ( array( 'edit_pages', 'publish_pages', 'edit_posts', 'publish_posts', 'upload_files', 'unfiltered_html' ) as $cap ) {
			$caps[ $cap ] = current_user_can( $cap );
		}
		$templates = wp_list_pluck( get_block_templates( array( 'slug__in' => AI_Canvas_Content::TEMPLATES ) ), 'slug' );

		return array(
			'site_name'                    => (string) get_bloginfo( 'name' ),
			'home_url'                     => home_url( '/' ),
			'theme'                        => array(
				'stylesheet'     => $theme->get_stylesheet(),
				'name'           => $theme->get( 'Name' ),
				'is_block_theme' => wp_is_block_theme(),
			),
			'user'                         => array(
				'id'    => $user->ID,
				'login' => $user->user_login,
				'roles' => array_values( $user->roles ),
			),
			'capabilities'                 => $caps,
			'can_write_scripts_and_styles' => $caps['unfiltered_html'],
			'templates'                    => array(
				'framed' => in_array( AI_Canvas_Content::TEMPLATE_FRAMED, $templates, true ),
				'blank'  => in_array( AI_Canvas_Content::TEMPLATE_BLANK, $templates, true ),
			),
			'alignfull_wrapper'            => AI_Canvas_Render::jvhe_active() ? 'jvhe' : 'ai-canvas',
			'max_upload_bytes'             => (int) wp_max_upload_size(),
			'plugin_version'               => AI_CANVAS_VERSION,
			'guidelines_version'           => AI_Canvas_Guidelines::VERSION,
		);
	}

	public static function find( $input = array() ) {
		$status = $input['status'] ?? 'any';
		$args   = array(
			'post_type'      => $input['post_type'] ?? 'page',
			'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ) : $status,
			'posts_per_page' => max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
			's'              => $input['search'] ?? '',
		);
		$posts  = array_filter( get_posts( $args ), fn( $post ) => current_user_can( 'edit_post', $post->ID ) );

		return array( 'items' => array_values( array_map( fn( $post ) => AI_Canvas_Content::describe( $post->ID ), $posts ) ) );
	}

	public static function get( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$latest = wp_get_latest_revision_id_and_total_count( $post->ID );
		$check  = AI_Canvas_Content::validate( $post->post_content );

		return AI_Canvas_Content::describe( $post->ID ) + array(
			'content'            => $post->post_content,
			'bytes'              => strlen( $post->post_content ),
			'warnings'           => $check['error'] ? array( $check['error']->get_error_code() . ': ' . $check['error']->get_error_message() ) : $check['warnings'],
			'revision_count'     => is_wp_error( $latest ) ? 0 : (int) $latest['count'],
			'latest_revision_id' => is_wp_error( $latest ) ? null : (int) $latest['latest_id'],
		);
	}

	public static function create( $input = array() ) {
		$post_type = $input['post_type'] ?? 'page';
		$check     = AI_Canvas_Content::validate( (string) $input['content'] );
		if ( $check['error'] ) {
			return $check['error'];
		}

		$template = $input['template'] ?? ( 'page' === $post_type ? 'framed' : 'theme' );
		$meta     = array( AI_Canvas_Content::MARKER_META => '1' );
		$slug     = AI_Canvas_Content::template_slug( $template );
		if ( $slug ) {
			$meta['_wp_page_template'] = $slug;
		}

		$postarr = array(
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => $input['content'],
			'post_type'    => $post_type,
			'post_status'  => $input['status'] ?? 'publish',
			'post_name'    => sanitize_title( $input['slug'] ?? '' ),
			'post_parent'  => (int) ( $input['parent'] ?? 0 ),
			'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
			'meta_input'   => $meta,
		);

		// wp_insert_post() expects slashed data; without this JS "\n" and "\d" are eaten.
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		AI_Canvas_Cache::purge( $post_id, self::change( 'create', $post_id ) );

		return AI_Canvas_Content::describe( $post_id ) + array( 'warnings' => $check['warnings'] );
	}

	public static function update( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$postarr  = array( 'ID' => $post->ID );
		$warnings = array();

		if ( isset( $input['content'] ) ) {
			$check = AI_Canvas_Content::validate( (string) $input['content'] );
			if ( $check['error'] ) {
				return $check['error'];
			}
			$warnings                = $check['warnings'];
			$postarr['post_content'] = $input['content'];
		}
		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['status'] ) ) {
			$postarr['post_status'] = $input['status'];
		}
		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = (int) $input['parent'];
		}
		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( count( $postarr ) < 2 && ! isset( $input['template'] ) ) {
			return new WP_Error( 'ai_canvas_nothing_to_update', 'Nothing to update: pass content, title, status, slug, template, parent, or excerpt.' );
		}

		$before = wp_get_latest_revision_id_and_total_count( $post->ID );
		$before = is_wp_error( $before ) ? 0 : (int) $before['latest_id'];

		if ( count( $postarr ) > 1 ) {
			$result = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $input['template'] ) ) {
			$slug = AI_Canvas_Content::template_slug( $input['template'] );
			if ( $slug ) {
				update_post_meta( $post->ID, '_wp_page_template', $slug );
			} else {
				delete_post_meta( $post->ID, '_wp_page_template' );
			}
		}
		AI_Canvas_Content::mark( $post->ID );

		$after = wp_get_latest_revision_id_and_total_count( $post->ID );
		$after = is_wp_error( $after ) ? 0 : (int) $after['latest_id'];

		AI_Canvas_Cache::purge( $post->ID, self::change( 'update', $post->ID, $after ) );

		return AI_Canvas_Content::describe( $post->ID ) + array(
			'revision_id' => $after ?: null,
			'changed'     => $after !== $before,
			'warnings'    => $warnings,
		);
	}

	public static function list_revisions( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$revisions = wp_get_post_revisions(
			$post->ID,
			array( 'posts_per_page' => max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) ) )
		);

		return array(
			'revisions' => array_values(
				array_map(
					fn( $rev ) => array(
						'revision_id'     => $rev->ID,
						'modified_gmt'    => (string) $rev->post_modified_gmt,
						'author'          => (string) get_the_author_meta( 'display_name', (int) $rev->post_author ),
						'bytes'           => strlen( $rev->post_content ),
						'matches_current' => $rev->post_content === $post->post_content,
					),
					$revisions
				)
			),
		);
	}

	public static function restore_revision( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$revision_id = (int) $input['revision_id'];
		$revision    = wp_get_post_revision( $revision_id );
		if ( ! $revision || (int) $revision->post_parent !== $post->ID ) {
			return new WP_Error( 'ai_canvas_bad_revision', sprintf( 'Revision %d does not belong to post %d. Use list-revisions.', (int) $input['revision_id'], $post->ID ) );
		}
		// Restore goes through wp_update_post(), so kses applies to the old content too.
		if ( AI_Canvas_Content::needs_unfiltered_html( $revision->post_content ) && ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'ai_canvas_unfiltered_html_required', 'This connection user is not allowed to publish scripts and styles, so restoring this revision would strip its interactivity.' );
		}

		$result = wp_restore_post_revision( $revision->ID );
		if ( ! $result ) {
			return new WP_Error( 'ai_canvas_restore_failed', 'WordPress refused to restore the revision.' );
		}

		$after = wp_get_latest_revision_id_and_total_count( $post->ID );
		$after = is_wp_error( $after ) ? null : (int) $after['latest_id'];

		AI_Canvas_Cache::purge( $post->ID, self::change( 'restore', $post->ID, $after ) );

		return AI_Canvas_Content::describe( $post->ID ) + array(
			'restored_revision_id' => $revision->ID,
			'revision_id'          => $after,
		);
	}

	public static function trash( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'ai_canvas_trash_failed', 'WordPress refused to trash the post.' );
		}
		AI_Canvas_Cache::purge( $post->ID, self::change( 'trash', $post->ID ) );

		return array(
			'id'     => $post->ID,
			'status' => (string) get_post_status( $post->ID ),
			'title'  => (string) get_the_title( $post->ID ),
		);
	}

	public static function check( $input = array() ) {
		$post = self::target( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$link   = (string) get_permalink( $post->ID );
		$status = (string) $post->post_status;
		$facts  = array(
			'link'        => $link,
			'status'      => $status,
			'http'        => null,
			'public'      => null,
			'fetched_via' => 'http',
			'error'       => null,
		);

		$response = wp_remote_get(
			$link,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'sslverify'   => 'local' !== wp_get_environment_type(),
				'user-agent'  => 'ai-canvas-check',
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$code           = (int) wp_remote_retrieve_response_code( $response );
			$facts['http']  = $code;
			$facts['public'] = 200 === $code && 'publish' === $status;
			return $facts + AI_Canvas_Content::render_facts( (string) wp_remote_retrieve_body( $response ) );
		}

		// The site cannot reach itself (blocked loopback, saturated pool): render
		// in-process instead so the content facts are still meaningful.
		$facts['error']       = $response->get_error_message();
		$facts['fetched_via'] = 'in-process';
		$facts['public']      = 'publish' === $status ? null : false;

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$html = (string) apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();

		return $facts + AI_Canvas_Content::render_facts( $html );
	}

	// --- helpers -----------------------------------------------------------.

	/**
	 * Resolve the target page/post from `id`, refusing revisions/attachments.
	 */
	private static function target( array $input ) {
		$post = get_post( (int) ( $input['id'] ?? 0 ) );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'ai_canvas_not_found', sprintf( 'No page or post with id %d. Use find-pages.', (int) ( $input['id'] ?? 0 ) ) );
		}
		return $post;
	}

	private static function change( string $action, int $post_id, ?int $revision_id = null ): array {
		return array(
			'action'      => $action,
			'post_id'     => $post_id,
			'revision_id' => $revision_id,
			'user'        => get_current_user_id(),
			'time'        => time(),
		);
	}
}
