<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the real `uninstall.php` end-to-end: every option, user-meta and post-meta key the
 * `footprint.php` manifest lists is gone after it runs, and a sentinel key NOT in the footprint
 * survives — proving the file deletes what it owns and nothing else.
 *
 * `uninstall.php` and `footprint.php` both guard on `defined( 'WP_UNINSTALL_PLUGIN' )`, a constant
 * WordPress itself only defines during a real plugin-delete request. This test defines it by hand
 * ahead of the manifest it reads to build its expectations, so the one test method runs
 * `#[RunInSeparateProcess]` — the constant must not leak into the rest of the suite, where its
 * presence would be indistinguishable from an actual uninstall.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class UninstallTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * The ID of the page the post-meta sentinels are seeded on, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     int|null
	 */
	private ?int $proof_page_id = null;

	// endregion.

	// region LIFECYCLE.

	/**
	 * Removes the canary and the proof page regardless of how the test finished, since this suite
	 * runs against a persistent wp-env database with no per-test transaction rollback (see
	 * tests/README.md).
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		delete_option( 'a8csp_ai_canvas_test_uninstall_canary' );
		if ( null !== $this->proof_page_id ) {
			wp_delete_post( $this->proof_page_id, true );
		}

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * Seeds a sentinel for every key the real footprint lists plus the canary, runs the real
	 * `uninstall.php`, then asserts the footprint's keys are gone and the canary survived. The
	 * loops read the same `footprint.php` manifest `uninstall.php` deletes from, so the proof
	 * tracks the footprint as it grows with no per-key test edits — an empty footprint section
	 * simply loops zero times.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_uninstall_deletes_only_its_own_footprint(): void {
		\define( 'WP_UNINSTALL_PLUGIN', true );

		$footprint = require \dirname( __DIR__, 2 ) . '/footprint.php';
		$user_id   = self::an_existing_user_id();

		foreach ( $footprint['options'] as $option ) {
			update_option( $option, 'sentinel' );
		}

		foreach ( $footprint['user_meta'] as $meta_key ) {
			update_user_meta( $user_id, $meta_key, 'sentinel' );
		}

		$this->proof_page_id = self::a_proof_page_id();
		foreach ( $footprint['post_meta'] as $post_meta_key ) {
			update_post_meta( $this->proof_page_id, $post_meta_key, 'sentinel' );
		}

		update_post_meta( $this->proof_page_id, 'a8csp_ai_canvas_test_uninstall_canary', 'sentinel' );
		update_option( 'a8csp_ai_canvas_test_uninstall_canary', 'sentinel' );

		foreach ( array( 'stable', 'prerelease' ) as $channel ) {
			set_transient( 'a8csp_ai_canvas_github_latest_release_' . $channel, 'sentinel', HOUR_IN_SECONDS );
		}

		require \dirname( __DIR__, 2 ) . '/uninstall.php';

		foreach ( $footprint['options'] as $option ) {
			self::assertFalse( get_option( $option ), "uninstall.php must delete the '{$option}' option" );
		}

		foreach ( array( 'stable', 'prerelease' ) as $channel ) {
			self::assertFalse( get_transient( 'a8csp_ai_canvas_github_latest_release_' . $channel ), "uninstall.php must delete the '{$channel}' update-check transient" );
		}

		foreach ( $footprint['user_meta'] as $meta_key ) {
			self::assertSame( '', get_user_meta( $user_id, $meta_key, true ), "uninstall.php must delete the '{$meta_key}' user-meta key" );
		}

		foreach ( $footprint['post_meta'] as $post_meta_key ) {
			self::assertSame( '', get_post_meta( $this->proof_page_id, $post_meta_key, true ), "uninstall.php must delete the '{$post_meta_key}' post-meta key" );
		}

		self::assertSame( 'sentinel', get_post_meta( $this->proof_page_id, 'a8csp_ai_canvas_test_uninstall_canary', true ), 'uninstall.php must not delete post meta outside its footprint' );
		self::assertSame( 'sentinel', get_option( 'a8csp_ai_canvas_test_uninstall_canary' ), 'uninstall.php must not delete keys outside its footprint' );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Returns an existing user's ID to seed and verify user-meta deletion against. wp-env's
	 * fixture always provisions the default admin (ID 1); querying for one keeps the test
	 * independent of that assumption instead of hard-coding it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function an_existing_user_id(): int {
		$users = get_users(
			array(
				'number' => 1,
				'fields' => 'ID',
			)
		);

		self::assertNotEmpty( $users, 'wp-env must provision at least one user to seed user-meta against' );

		return (int) $users[0];
	}

	/**
	 * Creates the page the post-meta sentinels are seeded on and returns its ID. The page itself
	 * is not the plugin's to delete: only the footprint's meta keys on it are.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function a_proof_page_id(): int {
		$page_id = wp_insert_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Uninstall proof',
			)
		);

		self::assertGreaterThan( 0, $page_id, 'wp-env must allow creating a page to seed post meta against' );

		return $page_id;
	}

	// endregion.
}
