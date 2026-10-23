<?php declare( strict_types=1 );

use A8C\SpecialProjects\PluginTemplate\Plugin;

\defined( 'ABSPATH' ) || exit;

// region META

// This accessor and the prefixed `get_plugin_slug()`, `get_plugin_name()` and `get_plugin_version()`
// getters in functions-bootstrap.php are the plugin's supported surface for a peer plugin; the
// kernel classes behind them carry `@internal` and may change shape without notice.

/**
 * Returns the plugin's composition root.
 *
 * Construction only — never boots: a peer calling this at include time would otherwise run the
 * component gates before every plugin has loaded. Booting stays tied to the `plugins_loaded`
 * attachment in the main plugin file.
 *
 * @api
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function a8csp_template_plugin(): Plugin {
	static $plugin   = null;
	return $plugin ??= new Plugin();
}

// endregion

// region OTHER

$a8csp_template_includes = \glob( \constant( 'A8CSP_TEMPLATE_DIR_PATH' ) . 'includes/*.php' );
if ( false !== $a8csp_template_includes ) {
	\sort( $a8csp_template_includes );
	foreach ( $a8csp_template_includes as $a8csp_template_include ) {
		if ( \str_starts_with( \basename( $a8csp_template_include ), '_' ) ) {
			continue; // An underscore prefix opts a file out of automatic loading.
		}

		require_once $a8csp_template_include;
	}
}

// endregion
