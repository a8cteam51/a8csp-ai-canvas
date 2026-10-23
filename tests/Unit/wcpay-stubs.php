<?php declare( strict_types=1 );

/**
 * Minimal WooPayments stand-in for Unit tests that open the WooPayments integration's companion
 * gate outside WordPress: the constant WooPayments' main file defines when it is included, before
 * WooPayments declares its main class. The guard keeps the file inert wherever the real plugin is
 * loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \defined( 'WCPAY_PLUGIN_FILE' ) ) {
	\define( 'WCPAY_PLUGIN_FILE', __FILE__ );
}
