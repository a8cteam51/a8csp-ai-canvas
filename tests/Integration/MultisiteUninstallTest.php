<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Integration;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the real `uninstall.php` against a multisite network: a plugin deletion runs the file
 * only once, network-wide, so its options sweep must clean every site — not just the site the
 * uninstall request runs on. Seeds the footprint's options on the main site and on a second site
 * created for the proof, runs the real `uninstall.php`, then asserts both sites come back clean
 * while a canary key survives on each.
 *
 * The proof is meaningful only on a converted network, so it self-skips in the standard
 * `composer test:integration` run (that fixture is single-site); `composer test:multisite` runs
 * it against the multisite fixture. It defines `WP_UNINSTALL_PLUGIN` by hand ahead of the guarded
 * manifest it reads, so the test method runs `#[RunInSeparateProcess]` for the same containment
 * reason as `UninstallTest`.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class MultisiteUninstallTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * The path of the second site this test creates. Leftovers under this path from an aborted
	 * earlier run are swept before creating it again, since this suite runs against a persistent
	 * wp-env database with no per-test transaction rollback (see tests/README.md).
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	private const string PROOF_SITE_PATH = '/uninstall-sweep-proof/';

	/**
	 * The ID of the second site created for the sweep proof, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     int|null
	 */
	private ?int $proof_site_id = null;

	// endregion.

	// region LIFECYCLE.

	/**
	 * Removes the canary and the proof site regardless of how the test finished, for the same
	 * persistent-database reason the proof-site path is swept on entry.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		delete_option( 'a8csp_template_test_uninstall_canary' );

		if ( null !== $this->proof_site_id ) {
			wp_delete_site( $this->proof_site_id );
		}

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * Seeds a sentinel for every option the real footprint lists — plus the canary — on both the
	 * main site and a freshly created second site, runs the real `uninstall.php`, then asserts
	 * every site's footprint options are gone and every site's canary survived.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_uninstall_sweeps_the_options_of_every_site(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'This proof only runs against a multisite network; this fixture is single-site.' );
		}

		\define( 'WP_UNINSTALL_PLUGIN', true );

		$footprint           = require \dirname( __DIR__, 2 ) . '/footprint.php';
		$this->proof_site_id = self::a_fresh_proof_site_id();

		foreach ( array( get_current_blog_id(), $this->proof_site_id ) as $site_id ) {
			switch_to_blog( $site_id );

			foreach ( $footprint['options'] as $option ) {
				update_option( $option, 'sentinel' );
			}
			update_option( 'a8csp_template_test_uninstall_canary', 'sentinel' );

			foreach ( array( 'stable', 'prerelease' ) as $channel ) {
				set_transient( 'a8csp_template_github_latest_release_' . $channel, 'sentinel', HOUR_IN_SECONDS );
			}

			restore_current_blog();
		}

		require \dirname( __DIR__, 2 ) . '/uninstall.php';

		foreach ( array( get_current_blog_id(), $this->proof_site_id ) as $site_id ) {
			switch_to_blog( $site_id );

			foreach ( $footprint['options'] as $option ) {
				self::assertFalse( get_option( $option ), "uninstall.php must delete the '{$option}' option on site {$site_id}" );
			}
			foreach ( array( 'stable', 'prerelease' ) as $channel ) {
				self::assertFalse( get_transient( 'a8csp_template_github_latest_release_' . $channel ), "uninstall.php must delete the '{$channel}' update-check transient on site {$site_id}" );
			}
			self::assertSame( 'sentinel', get_option( 'a8csp_template_test_uninstall_canary' ), "uninstall.php must not delete keys outside its footprint on site {$site_id}" );

			restore_current_blog();
		}
	}

	// endregion.

	// region HELPERS.

	/**
	 * Creates the second site the sweep proof runs against and returns its ID, deleting any
	 * leftover site under the proof path first so the creation cannot collide with the remains
	 * of an aborted earlier run.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function a_fresh_proof_site_id(): int {
		$leftover_site_ids = get_sites(
			array(
				'path'   => self::PROOF_SITE_PATH,
				'fields' => 'ids',
			)
		);
		foreach ( $leftover_site_ids as $leftover_site_id ) {
			wp_delete_site( (int) $leftover_site_id );
		}

		$network = get_network();
		self::assertInstanceOf( \WP_Network::class, $network );

		$proof_site_id = wp_insert_site(
			array(
				'domain' => $network->domain,
				'path'   => self::PROOF_SITE_PATH,
			)
		);

		self::assertIsInt( $proof_site_id, 'the multisite fixture must allow creating a second site for the sweep proof' );

		return $proof_site_id;
	}

	// endregion.
}
