<?php declare( strict_types=1 );

\defined( 'ABSPATH' ) || exit;

/**
 * Returns the example option's value.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function a8csp_ai_webmcp_get_example_option(): string {
	return (string) get_option( 'a8csp_ai_webmcp_example_option', '' );
}
