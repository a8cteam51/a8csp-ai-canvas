<?php declare( strict_types=1 );
/**
 * The plugin's persisted-footprint manifest: every durable option, user-meta and post-meta key any
 * component writes, grouped by owning component so ownership stays reviewable; transient cache entries are deleted
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
 * @package     A8C\SpecialProjects\AICanvas
 */

\defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

return array(
	'options'   => array(),
	'user_meta' => array(),
	'post_meta' => array(
		// PageAccess\Flag owns:
		'_a8csp_ai_canvas',
	),
);
