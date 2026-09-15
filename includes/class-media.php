<?php
/**
 * Media Library tools: upload and search, returning image dimensions and
 * generated sizes so agents can reference right-sized variants.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Media {

	public static function upload( $input = array() ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$max_bytes = wp_max_upload_size();

		if ( ! empty( $input['url'] ) ) {
			$tmp = download_url( $input['url'] );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			if ( wp_filesize( $tmp ) > $max_bytes ) {
				wp_delete_file( $tmp );
				return new WP_Error( 'ai_canvas_too_large', sprintf( 'Download exceeds the %d byte upload limit.', $max_bytes ) );
			}
			$name = $input['filename'] ?? basename( (string) wp_parse_url( $input['url'], PHP_URL_PATH ) );
		} elseif ( ! empty( $input['base64'] ) && ! empty( $input['filename'] ) ) {
			// Check the encoded length before decoding so an oversized payload
			// never allocates its decoded form (base64 inflates ~4/3).
			if ( strlen( $input['base64'] ) > ( $max_bytes * 4 / 3 ) + 1024 ) {
				return new WP_Error( 'ai_canvas_too_large', sprintf( 'Upload exceeds the %d byte upload limit.', $max_bytes ) );
			}
			$contents = base64_decode( $input['base64'], true );
			if ( false === $contents ) {
				return new WP_Error( 'ai_canvas_bad_base64', 'base64 could not be decoded.' );
			}
			if ( strlen( $contents ) > $max_bytes ) {
				return new WP_Error( 'ai_canvas_too_large', sprintf( 'Upload exceeds the %d byte upload limit.', $max_bytes ) );
			}
			$name = sanitize_file_name( $input['filename'] );
			$tmp  = wp_tempnam( $name );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- staging a temp file that media_handle_sideload() moves into uploads.
			if ( ! $tmp || false === file_put_contents( $tmp, $contents ) ) {
				if ( $tmp ) {
					wp_delete_file( $tmp );
				}
				return new WP_Error( 'ai_canvas_tmp_failed', 'Could not stage the upload.' );
			}
		} else {
			return new WP_Error( 'ai_canvas_bad_input', 'Provide either url, or base64 with filename.' );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0,
			$input['title'] ?? null
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		return self::describe( $attachment_id );
	}

	public static function search( $input = array() ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) ),
			's'              => $input['search'] ?? '',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$attachments = get_posts( $args );

		return array(
			'media' => array_map( fn( $att ) => self::describe( $att->ID ), $attachments ),
		);
	}

	/**
	 * Attachment shape shared by upload and search. Dimensions and generated
	 * sizes exist so agents can reference a right-sized variant with explicit
	 * width/height instead of the full-size original.
	 */
	public static function describe( int $attachment_id ): array {
		$meta  = wp_get_attachment_metadata( $attachment_id );
		$sizes = array();
		foreach ( array_keys( $meta['sizes'] ?? array() ) as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $src ) {
				$sizes[] = array(
					'name'   => $size,
					'url'    => $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime'          => (string) get_post_mime_type( $attachment_id ),
			'title'         => get_the_title( $attachment_id ),
			'alt'           => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'         => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'        => isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'sizes'         => $sizes,
		);
	}

	public static function schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'attachment_id' => array( 'type' => 'integer' ),
				'url'           => array( 'type' => 'string' ),
				'mime'          => array( 'type' => 'string' ),
				'title'         => array( 'type' => 'string' ),
				'alt'           => array( 'type' => 'string' ),
				'width'         => array( 'type' => array( 'integer', 'null' ) ),
				'height'        => array( 'type' => array( 'integer', 'null' ) ),
				'sizes'         => array(
					'type'  => 'array',
					'items' => array(
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
	}
}
