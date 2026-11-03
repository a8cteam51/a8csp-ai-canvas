<?php declare( strict_types=1 );

use A8C\SpecialProjects\AICanvas\Plugin;

\defined( 'ABSPATH' ) || exit;

// region META

/**
 * Returns the plugin's composition root.
 *
 * Construction only; it never boots the plugin.
 *
 * @api
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function a8csp_ai_canvas_plugin(): Plugin {
	static $plugin   = null;
	return $plugin ??= new Plugin();
}

// endregion

// region OTHER

$a8csp_ai_canvas_includes = \glob( \constant( 'A8CSP_AI_CANVAS_DIR_PATH' ) . 'includes/*.php' );
if ( false !== $a8csp_ai_canvas_includes ) {
	\sort( $a8csp_ai_canvas_includes );
	foreach ( $a8csp_ai_canvas_includes as $a8csp_ai_canvas_include ) {
		if ( \str_starts_with( \basename( $a8csp_ai_canvas_include ), '_' ) ) {
			continue; // An underscore prefix opts a file out of automatic loading.
		}

		require_once $a8csp_ai_canvas_include;
	}
}

// endregion
