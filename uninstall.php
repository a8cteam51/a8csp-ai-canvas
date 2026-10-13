<?php declare( strict_types=1 );
/**
 * Uninstall handler. WordPress runs this file directly when the plugin is deleted, in a cold
 * bootstrap without the plugin loaded, so the durable keys it deletes come from a dependency-free
 * manifest.
 *
 * @since       1.0.0
 * @version     1.0.0
 * @package     A8C\SpecialProjects\AICanvas
 */

\defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The persisted footprint lives in the dependency-free `footprint.php` manifest, which the uninstall proofs read too.
$a8csp_ai_canvas_footprint = require __DIR__ . '/footprint.php';

/*
 * Options and post meta are stored per site, and a multisite uninstall runs only once,
 * network-wide — so the sweep visits every site of the network. It pages through the sites in
 * batches so memory stays flat on large networks while the sweep still visits every site.
 */
if ( is_multisite() ) {
	$a8csp_ai_canvas_uninstall_offset = 0;

	do {
		$a8csp_ai_canvas_uninstall_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $a8csp_ai_canvas_uninstall_offset,
			)
		);

		foreach ( $a8csp_ai_canvas_uninstall_site_ids as $a8csp_ai_canvas_uninstall_site_id ) {
			switch_to_blog( $a8csp_ai_canvas_uninstall_site_id );

			foreach ( $a8csp_ai_canvas_footprint['options'] as $a8csp_ai_canvas_uninstall_option ) {
				delete_option( $a8csp_ai_canvas_uninstall_option );
			}
			foreach ( $a8csp_ai_canvas_footprint['post_meta'] as $a8csp_ai_canvas_uninstall_meta_key ) {
				delete_post_meta_by_key( $a8csp_ai_canvas_uninstall_meta_key );
			}
			delete_transient( 'a8csp_ai_canvas_github_latest_release_stable' );
			delete_transient( 'a8csp_ai_canvas_github_latest_release_prerelease' );

			restore_current_blog();
		}

		$a8csp_ai_canvas_uninstall_offset    += 100;
		$a8csp_ai_canvas_uninstall_batch_size = \count( $a8csp_ai_canvas_uninstall_site_ids );
	} while ( 100 === $a8csp_ai_canvas_uninstall_batch_size );
} else {
	foreach ( $a8csp_ai_canvas_footprint['options'] as $a8csp_ai_canvas_uninstall_option ) {
		delete_option( $a8csp_ai_canvas_uninstall_option );
	}
	foreach ( $a8csp_ai_canvas_footprint['post_meta'] as $a8csp_ai_canvas_uninstall_meta_key ) {
		delete_post_meta_by_key( $a8csp_ai_canvas_uninstall_meta_key );
	}
	delete_transient( 'a8csp_ai_canvas_github_latest_release_stable' );
	delete_transient( 'a8csp_ai_canvas_github_latest_release_prerelease' );
}

// User meta is stored network-globally, so one pass covers every site.
foreach ( $a8csp_ai_canvas_footprint['user_meta'] as $a8csp_ai_canvas_uninstall_meta_key ) {
	delete_metadata( 'user', 0, $a8csp_ai_canvas_uninstall_meta_key, '', true );
}
