<?php declare( strict_types=1 );

/**
 * Recording `add_action()` and `add_filter()` stubs for Unit tests that boot the real component
 * list outside WordPress. Each stub appends its hook name to the
 * `$GLOBALS['a8csp_template_test_hooks']` ledger so tests can assert which hooks a boot registered,
 * and appends the registered callback to `$GLOBALS['a8csp_template_test_hook_callbacks'][ $hook ]`
 * so a test can fetch a staged callback and invoke it to assert its behavior. The
 * `function_exists()` guards keep this file inert wherever WordPress is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \function_exists( 'add_action' ) ) {
	/**
	 * Records an action registration in the test ledgers.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string   $hook_name     The action hook name.
	 * @param   callable $callback      The callback, recorded so a test can fetch and invoke it.
	 * @param   int      $priority      The priority (ignored).
	 * @param   int      $accepted_args The accepted argument count (ignored).
	 *
	 * @return  true
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['a8csp_template_test_hooks'][]                        = $hook_name;
		$GLOBALS['a8csp_template_test_hook_callbacks'][ $hook_name ][] = $callback;
		return true;
	}
}

if ( ! \function_exists( 'add_filter' ) ) {
	/**
	 * Records a filter registration in the test ledgers.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string   $hook_name     The filter hook name.
	 * @param   callable $callback      The callback, recorded so a test can fetch and invoke it.
	 * @param   int      $priority      The priority (ignored).
	 * @param   int      $accepted_args The accepted argument count (ignored).
	 *
	 * @return  true
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['a8csp_template_test_hooks'][]                        = $hook_name;
		$GLOBALS['a8csp_template_test_hook_callbacks'][ $hook_name ][] = $callback;
		return true;
	}
}
