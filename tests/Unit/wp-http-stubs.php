<?php declare( strict_types=1 );

/**
 * HTTP and transient stubs for Unit tests that exercise the GitHub release updater outside
 * WordPress. `wp_remote_get()` records each requested URL in
 * `$GLOBALS['a8csp_template_test_http_requests']` and returns
 * `$GLOBALS['a8csp_template_test_http_response']`; the transient stubs read and write
 * `$GLOBALS['a8csp_template_test_transients']`, recording each TTL in
 * `$GLOBALS['a8csp_template_test_transient_ttls']` — so a test can stage the cache and the
 * network and observe every side effect. The `function_exists()` guards keep this file inert
 * wherever WordPress is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

\defined( 'HOUR_IN_SECONDS' ) || \define( 'HOUR_IN_SECONDS', 3600 );
\defined( 'MINUTE_IN_SECONDS' ) || \define( 'MINUTE_IN_SECONDS', 60 );

if ( ! \function_exists( 'get_transient' ) ) {
	/**
	 * Returns the staged transient value, or false when none is staged.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $transient The transient key to look up.
	 *
	 * @return  mixed
	 */
	function get_transient( $transient ) {
		return $GLOBALS['a8csp_template_test_transients'][ $transient ] ?? false;
	}
}

if ( ! \function_exists( 'set_transient' ) ) {
	/**
	 * Stores the transient value in the test ledger, recording its TTL.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $transient  The transient key.
	 * @param   mixed  $value      The value to store.
	 * @param   int    $expiration The TTL in seconds.
	 *
	 * @return  true
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['a8csp_template_test_transients'][ $transient ]     = $value;
		$GLOBALS['a8csp_template_test_transient_ttls'][ $transient ] = $expiration;
		return true;
	}
}

if ( ! \function_exists( 'wp_remote_get' ) ) {
	/**
	 * Records the requested URL and returns the staged HTTP response.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $url The URL being requested.
	 *
	 * @return  array<string, mixed>
	 */
	function wp_remote_get( $url ) {
		$GLOBALS['a8csp_template_test_http_requests'][] = $url;
		return $GLOBALS['a8csp_template_test_http_response'];
	}
}

if ( ! \function_exists( 'is_wp_error' ) ) {
	/**
	 * Reports whether the value is a WP_Error; the staged responses are arrays, so this is
	 * false unless a test stages an actual error double.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   mixed $thing The value to check.
	 *
	 * @return  bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! \function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Returns the staged response's status code.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $response The staged HTTP response.
	 *
	 * @return  int|string
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? '';
	}
}

if ( ! \function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Returns the staged response's body.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $response The staged HTTP response.
	 *
	 * @return  string
	 */
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}
