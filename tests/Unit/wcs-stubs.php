<?php declare( strict_types=1 );

/**
 * Minimal WooCommerce Subscriptions stand-in for Unit tests that open the Subscriptions
 * integration's companion gate outside WordPress. The guard keeps the file inert wherever the
 * real plugin is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \class_exists( 'WC_Subscriptions' ) ) {
	/**
	 * Stand-in for the WooCommerce Subscriptions main class; presence is all the companion gate
	 * checks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	class WC_Subscriptions {}
}
