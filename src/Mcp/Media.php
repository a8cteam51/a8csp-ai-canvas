<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Mcp;

\defined( 'ABSPATH' ) || exit;

/**
 * The Media Library operations behind the MCP tools: search the library, and add a file to it
 * from a URL or from base64 data.
 *
 * Every result carries the image's pixel dimensions and its generated smaller sizes, so a client
 * can reference a right-sized variant with explicit dimensions instead of the full-size original.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Media {
	// region METHODS

	/**
	 * Whether the current user may use the media tools at all.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	public function can_use(): bool {
		return current_user_can( 'edit_pages' ) && current_user_can( 'upload_files' );
	}

	/**
	 * Searches the Media Library, most recent first.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{search?: string, limit?: int} $input The tool input.
	 *
	 * @return  array{media: list<array<string, mixed>>}
	 */
	public function list( array $input ): array {
		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => \max( 1, \min( 100, $input['limit'] ?? 20 ) ),
				's'              => $input['search'] ?? '',
				'fields'         => 'ids',
			)
		);

		return array( 'media' => \array_map( $this->describe( ... ), \array_values( $attachment_ids ) ) );
	}

	/**
	 * Adds a file to the Media Library from a URL, or from base64 data with a filename.
	 *
	 * WordPress decides whether the file type is allowed, as it does for any upload.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array{url?: string, base64?: string, filename?: string, title?: string, alt?: string} $input The tool input.
	 *
	 * @return  array<string, mixed>|\WP_Error
	 */
	public function upload( array $input ): array|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url      = $input['url'] ?? '';
		$base64   = $input['base64'] ?? '';
		$filename = sanitize_file_name( $input['filename'] ?? '' );

		if ( '' !== $url ) {
			$file = $this->stage_download( $url, $filename );
		} elseif ( '' !== $base64 && '' !== $filename ) {
			$file = $this->stage_base64( $base64, $filename );
		} else {
			return new \WP_Error( 'a8csp_ai_canvas_invalid_media', __( 'Pass either url, or base64 together with filename.', 'a8csp-ai-canvas' ) );
		}
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$attachment_id = media_handle_sideload( $file, 0, $input['title'] ?? null );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file['tmp_name'] );
			return $attachment_id;
		}

		if ( isset( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		return $this->describe( $attachment_id );
	}

	// endregion

	// region HELPERS

	/**
	 * Downloads a remote file to a temporary file, refusing one over the site's upload limit.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $url      The file's URL.
	 * @param   string $filename The name to store it under, or an empty string to take it from the URL.
	 *
	 * @return  array{name: string, tmp_name: string}|\WP_Error
	 */
	private function stage_download( string $url, string $filename ): array|\WP_Error {
		$temporary_file = download_url( $url );
		if ( is_wp_error( $temporary_file ) ) {
			return $temporary_file;
		}

		if ( wp_filesize( $temporary_file ) > wp_max_upload_size() ) {
			wp_delete_file( $temporary_file );
			return $this->too_large();
		}

		return array(
			'name'     => '' !== $filename ? $filename : \basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $temporary_file,
		);
	}

	/**
	 * Decodes base64 data to a temporary file, refusing data over the site's upload limit.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $base64   The base64-encoded file contents.
	 * @param   string $filename The name to store the file under.
	 *
	 * @return  array{name: string, tmp_name: string}|\WP_Error
	 */
	private function stage_base64( string $base64, string $filename ): array|\WP_Error {
		$max_bytes = wp_max_upload_size();

		// Base64 inflates by a third: checking the encoded length first keeps an oversized payload
		// from ever being decoded into memory.
		if ( \strlen( $base64 ) > ( $max_bytes * 4 / 3 ) + 1024 ) {
			return $this->too_large();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a file the client sent as base64, the only way MCP tool input can carry one.
		$contents = \base64_decode( $base64, true );
		if ( false === $contents ) {
			return new \WP_Error( 'a8csp_ai_canvas_invalid_media', __( 'The base64 data could not be decoded.', 'a8csp-ai-canvas' ) );
		}
		if ( \strlen( $contents ) > $max_bytes ) {
			return $this->too_large();
		}

		$temporary_file = wp_tempnam( $filename );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fills the temporary file wp_tempnam() just created, as core's own upload handling does; WP_Filesystem is unavailable on hosts that need credentials for it.
		if ( false === \file_put_contents( $temporary_file, $contents ) ) {
			wp_delete_file( $temporary_file );
			return new \WP_Error( 'a8csp_ai_canvas_media_not_staged', __( 'WordPress could not write the file to its temporary directory.', 'a8csp-ai-canvas' ) );
		}

		return array(
			'name'     => $filename,
			'tmp_name' => $temporary_file,
		);
	}

	/**
	 * Returns the error for a file over the site's upload limit.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  \WP_Error
	 */
	private function too_large(): \WP_Error {
		return new \WP_Error(
			'a8csp_ai_canvas_media_too_large',
			/* translators: %s: The site's upload size limit, e.g. "8 MB" */
			\sprintf( __( 'The file is larger than this site’s upload limit of %s.', 'a8csp-ai-canvas' ), size_format( wp_max_upload_size() ) )
		);
	}

	/**
	 * Returns the attachment summary both media tools respond with.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $attachment_id The attachment ID.
	 *
	 * @return  array<string, mixed>
	 */
	private function describe( int $attachment_id ): array {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = \is_array( $metadata ) ? $metadata : array();

		$sizes = array();
		// A size's name is its array key, which PHP turns into an integer when it is numeric.
		foreach ( \array_map( \strval( ... ), \array_keys( $metadata['sizes'] ?? array() ) ) as $size ) {
			$source = wp_get_attachment_image_src( $attachment_id, $size );
			if ( \is_array( $source ) ) {
				$sizes[] = array(
					'name'   => $size,
					'url'    => $source[0],
					'width'  => $source[1],
					'height' => $source[2],
				);
			}
		}

		return array(
			'id'     => $attachment_id,
			'url'    => (string) wp_get_attachment_url( $attachment_id ),
			'mime'   => (string) get_post_mime_type( $attachment_id ),
			'title'  => get_the_title( $attachment_id ),
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'  => $metadata['width'] ?? null,
			'height' => $metadata['height'] ?? null,
			'sizes'  => $sizes,
		);
	}

	// endregion
}
