<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Mcp;

\defined( 'ABSPATH' ) || exit;

/**
 * The Media Library operation behind the MCP tools: search the library, so a client can reuse the
 * pictures the site already has.
 *
 * Nothing here adds to the library. A client can only put a file into tool input by writing its
 * bytes out, which does not work for a picture, so the site owner adds pictures to the library
 * and a page references them from there.
 *
 * Every result carries the image's pixel dimensions and its generated smaller sizes, so a client
 * can reference a right-sized variant with explicit dimensions instead of the full-size original.
 *
 * @since   1.0.0
 * @version 2.0.0
 */
final class Media {
	// region METHODS

	/**
	 * Whether the current user may use the media tool: browsing the Media Library takes the
	 * `upload_files` capability here, as it does in wp-admin.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
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

	// endregion

	// region HELPERS

	/**
	 * Returns the summary of an attachment the media tool responds with.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
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
