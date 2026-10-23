<?php declare( strict_types=1 );

/**
 * Minimal WooCommerce host stand-ins for Unit tests that boot the real component list outside
 * WordPress. Loading this file satisfies the plugin-wide host gate in `Plugin::boot()`; a test
 * that exercises a below-floor host defines `WC_VERSION` before loading it. The guards keep the
 * file inert wherever the real WooCommerce is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \class_exists( 'WooCommerce' ) ) {
	/**
	 * Stand-in for the WooCommerce main class; presence is all the host gate checks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	class WooCommerce {}
}

if ( ! \defined( 'WC_VERSION' ) ) {
	\define( 'WC_VERSION', '11.1.0' );
}
