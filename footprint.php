<?php declare( strict_types=1 );
/**
 * The plugin's persisted-footprint manifest: every durable option and user-meta key any component
 * writes, grouped by owning component so ownership stays reviewable; transient cache entries are deleted
 * directly by `uninstall.php`. Add a durable key here in the same change that introduces the write.
 *
 * WordPress's cold uninstall bootstrap loads this file (no autoloader, no Plugin or Component
 * classes), and the uninstall proofs load it too — so nothing here may reference plugin code. It is
 * pure data: a bare `return` of the manifest behind the same guard `uninstall.php` carries, so the
 * manifest is reachable only where it has a purpose. A proof that reads it to build its
 * expectations declares the uninstall constant first.
 *
 * @since       1.0.0
 * @version     1.0.0
 * @package     A8C\SpecialProjects\PluginTemplate
 */

\defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

return array(
	'options'   => array(
		// Settings owns:
		'a8csp_template_example_option',
		'a8csp_template_wc_example_option',
		// Integrations\WooCommerceSubscriptions\Component owns:
		'a8csp_template_wcs_example_option',
	),
	'user_meta' => array(),
);
