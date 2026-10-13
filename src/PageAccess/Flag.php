<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\PageAccess;

\defined( 'ABSPATH' ) || exit;

/**
 * The flag that opens a page to the MCP tools: one post meta key, read and written here, so every
 * part of the plugin agrees on what "open" means.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @see footprint.php
 */
final class Flag {
	// region FIELDS AND CONSTANTS

	/**
	 * The post meta key. Listed in the `footprint.php` manifest.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string META_KEY = '_a8csp_ai_canvas';

	// endregion

	// region METHODS

	/**
	 * Whether a page is open to the MCP tools.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $page_id The page ID.
	 *
	 * @return  bool
	 */
	public static function is_open( int $page_id ): bool {
		return '1' === get_post_meta( $page_id, self::META_KEY, true );
	}

	/**
	 * Opens a page to the MCP tools, or closes it. Only the flag is written: the page itself is
	 * not re-saved.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int  $page_id The page ID.
	 * @param   bool $open    Whether the page is open.
	 *
	 * @return  void
	 */
	public static function set( int $page_id, bool $open ): void {
		if ( $open ) {
			update_post_meta( $page_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $page_id, self::META_KEY );
		}
	}

	// endregion
}
