<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use A8C\SpecialProjects\AICanvas\Connector;
use PHPUnit\Framework\TestCase;

/**
 * Verifies what the Connectors-screen card is given to render: the MCP server URL, and the users
 * a client can connect as. Each test removes the users it creates, since this suite runs against a
 * persistent wp-env database with no per-test transaction rollback (see tests/README.md).
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class ConnectorTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * The IDs of the users a test created, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<int>
	 */
	private array $user_ids = array();

	/**
	 * The capability filters a test added, for tearDown to remove.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<\Closure>
	 */
	private array $capability_filters = array();

	// endregion.

	// region LIFECYCLE.

	/**
	 * Signs an administrator in: the Connectors screen is theirs alone.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$administrators = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		self::assertNotEmpty( $administrators, 'wp-env must provision an administrator' );

		wp_set_current_user( (int) $administrators[0] );
	}

	/**
	 * Removes the capability filters the test added, deletes the users it created, and takes the
	 * card's script back off the queue.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		foreach ( $this->capability_filters as $capability_filter ) {
			remove_filter( 'map_meta_cap', $capability_filter );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		wp_dequeue_script( 'a8csp-ai-canvas-connector' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * The card is given the MCP server URL and the current user's username to preselect.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_card_is_given_the_server_url_and_the_current_user(): void {
		$data = ( new Connector\Component() )->get_card_data();

		self::assertSame( rest_url( 'a8csp-ai-canvas/mcp' ), $data['serverUrl'] );
		self::assertSame( wp_get_current_user()->user_login, $data['currentUser'] );
	}

	/**
	 * The users offered are those who can edit pages: an editor is listed, an author is not. Each
	 * carries the link to where their application passwords are created — the administrator's own
	 * profile for themselves, the user's edit screen for anyone else.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_card_lists_the_users_who_can_edit_pages(): void {
		$editor_id = $this->a_user( 'a8csp-ai-canvas-proof-editor', 'editor' );
		$this->a_user( 'a8csp-ai-canvas-proof-author', 'author' );

		$users = \array_column( ( new Connector\Component() )->get_card_data()['users'], null, 'login' );

		self::assertArrayHasKey( wp_get_current_user()->user_login, $users );
		self::assertArrayHasKey( 'a8csp-ai-canvas-proof-editor', $users );
		self::assertArrayNotHasKey( 'a8csp-ai-canvas-proof-author', $users );

		self::assertSame( 'Proof a8csp-ai-canvas-proof-editor', $users['a8csp-ai-canvas-proof-editor']['name'] );
		self::assertSame( get_edit_user_link( $editor_id ) . '#application-passwords-section', $users['a8csp-ai-canvas-proof-editor']['passwordsUrl'] );
		self::assertStringContainsString( 'user-edit.php', $users['a8csp-ai-canvas-proof-editor']['passwordsUrl'] );
		self::assertStringContainsString( 'profile.php', $users[ wp_get_current_user()->user_login ]['passwordsUrl'] );
	}

	/**
	 * A user the administrator may not edit is still listed, but without a link to their profile.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_user_who_cannot_be_edited_carries_no_profile_link(): void {
		$this->a_user( 'a8csp-ai-canvas-proof-editor', 'editor' );

		$capability_filter          = static fn ( array $caps, string $cap ): array => 'edit_user' === $cap ? array( 'do_not_allow' ) : $caps;
		add_filter( 'map_meta_cap', $capability_filter, 10, 2 );
		$this->capability_filters[] = $capability_filter;

		$users = \array_column( ( new Connector\Component() )->get_card_data()['users'], null, 'login' );

		self::assertSame( '', $users['a8csp-ai-canvas-proof-editor']['passwordsUrl'] );
	}

	/**
	 * When core's Connectors screen initializes, the card's script is enqueued with its data
	 * printed ahead of it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_card_script_is_enqueued_on_the_connectors_screen(): void {
		self::assertFalse( wp_script_is( 'a8csp-ai-canvas-connector', 'enqueued' ) );

		( new Connector\Component() )->enqueue_card();

		self::assertTrue( wp_script_is( 'a8csp-ai-canvas-connector', 'enqueued' ) );

		$inline = wp_scripts()->get_data( 'a8csp-ai-canvas-connector', 'before' );
		self::assertIsArray( $inline );
		self::assertStringContainsString( 'window.a8cspAiCanvasConnector = {"serverUrl":', \implode( "\n", $inline ) );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Creates a user with the given role, registers them for cleanup, and returns their ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $login The username.
	 * @param   string $role  The role.
	 *
	 * @return  int
	 */
	private function a_user( string $login, string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password(),
				'display_name' => 'Proof ' . $login,
				'role'         => $role,
			)
		);
		self::assertIsInt( $user_id );
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	// endregion.
}
