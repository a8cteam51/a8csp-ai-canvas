<?php declare( strict_types=1 );

/**
 * PHPUnit bootstrap. Inside wp-env's `cli` container, also loads WP and the plugin entry file —
 * require_once is a no-op when WP already include_once'd the active plugin. Outside wp-env, where
 * no WordPress is present, it stands `ABSPATH` up for the production files' boot guard and loads
 * the recording hook stubs every Unit class needs, so a Unit class's own setup carries only the
 * extra stubs its proofs require.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

require_once __DIR__ . '/../vendor/autoload.php';

$a8csp_template_wp_load = '/var/www/html/wp-load.php';
if ( \file_exists( $a8csp_template_wp_load ) ) {
	require_once $a8csp_template_wp_load;
	require_once __DIR__ . '/../a8csp-template-plugin.php';
} elseif ( ! \defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', '/tmp/' );
	require_once __DIR__ . '/Unit/wp-hook-stubs.php';
}
