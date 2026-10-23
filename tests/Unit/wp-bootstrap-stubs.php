<?php declare( strict_types=1 );

/**
 * WordPress stubs for Unit tests that exercise the real `functions-bootstrap.php` helpers.
 * `did_action()` reads hook fire-counts from `$GLOBALS['a8csp_template_test_actions']` and
 * `get_plugin_data()` returns `$GLOBALS['a8csp_template_test_plugin_data']`, so a test can stage
 * the request timeline (which hooks fired, which headers exist) between calls. The
 * `function_exists()` guards keep this file inert wherever WordPress is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \function_exists( 'did_action' ) ) {
	/**
	 * Returns the staged fire-count for a hook.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $hook_name The hook name to look up.
	 *
	 * @return  int
	 */
	function did_action( $hook_name ) {
		return $GLOBALS['a8csp_template_test_actions'][ $hook_name ] ?? 0;
	}
}

if ( ! \function_exists( 'get_plugin_data' ) ) {
	/**
	 * Returns the staged plugin metadata, ignoring the file path like a canned parse would.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $plugin_file The plugin file path (ignored).
	 * @param   bool   $markup      Whether to apply markup (ignored).
	 * @param   bool   $translate   Whether to translate the data (ignored).
	 *
	 * @return  array<string, string>
	 */
	function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
		return $GLOBALS['a8csp_template_test_plugin_data'];
	}
}
