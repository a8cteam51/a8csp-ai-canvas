<?php declare( strict_types=1 );

use A8C\SpecialProjects\AICanvasWebMCP\Plugin;

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
function a8csp_ai_webmcp_plugin(): Plugin {
	static $plugin   = null;
	return $plugin ??= new Plugin();
}

// endregion

// region OTHER

$a8csp_ai_webmcp_includes = \glob( \constant( 'A8CSP_AI_WEBMCP_DIR_PATH' ) . 'includes/*.php' );
if ( false !== $a8csp_ai_webmcp_includes ) {
	\sort( $a8csp_ai_webmcp_includes );
	foreach ( $a8csp_ai_webmcp_includes as $a8csp_ai_webmcp_include ) {
		if ( \str_starts_with( \basename( $a8csp_ai_webmcp_include ), '_' ) ) {
			continue; // An underscore prefix opts a file out of automatic loading.
		}

		require_once $a8csp_ai_webmcp_include;
	}
}

// endregion
