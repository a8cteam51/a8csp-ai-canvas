<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use A8C\SpecialProjects\AICanvas\PageAccess\Flag;
use PHPUnit\Framework\TestCase;

/**
 * Verifies an editor's control over which pages are open to the MCP tools: the flag itself, the
 * route both switches read and save it through, its absence from core's page route, and the Pages
 * list column. Each test removes the pages and users it creates, since this suite runs against a
 * persistent wp-env database with no per-test transaction rollback (see tests/README.md).
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PageAccessTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * The IDs of the pages a test created, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<int>
	 */
	private array $page_ids = array();

	/**
	 * The IDs of the users a test created, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<int>
	 */
	private array $user_ids = array();

	// endregion.

	// region LIFECYCLE.

	/**
	 * Signs an administrator in.
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
	 * Deletes what the test created.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		foreach ( $this->page_ids as $page_id ) {
			wp_delete_post( $page_id, true );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * A page is closed until the flag is set, and closed again once it is cleared.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_flag_opens_and_closes_a_page(): void {
		$page_id = $this->a_page();

		self::assertFalse( Flag::is_open( $page_id ) );

		Flag::set( $page_id, true );
		self::assertTrue( Flag::is_open( $page_id ) );

		Flag::set( $page_id, false );
		self::assertFalse( Flag::is_open( $page_id ) );
	}

	/**
	 * Opening a page the tools did not create hands it to them, and closing it takes it back.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_tools_follow_the_flag(): void {
		$page_id  = $this->a_page();
		$get_page = wp_get_ability( 'a8csp-ai-canvas/get-page' );
		self::assertInstanceOf( \WP_Ability::class, $get_page );

		Flag::set( $page_id, true );
		$opened = $get_page->execute( array( 'id' => $page_id ) );
		self::assertIsArray( $opened );
		self::assertStringContainsString( 'The site owner wrote this.', $opened['content'] );

		Flag::set( $page_id, false );
		$closed = $get_page->execute( array( 'id' => $page_id ) );
		self::assertInstanceOf( \WP_Error::class, $closed );
		self::assertSame( 'a8csp_ai_canvas_page_not_open', $closed->get_error_code() );
	}

	/**
	 * An editor reads and switches the flag through the route, which writes the flag and leaves
	 * the page itself alone: its content and its modified time stay as they were.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_an_editor_switches_the_flag_through_the_route(): void {
		$page_id = $this->a_page();
		$before  = get_post( $page_id );
		self::assertInstanceOf( \WP_Post::class, $before );

		wp_set_current_user( $this->a_user_id( 'editor' ) );

		self::assertSame( array( 'open' => false ), self::get_access( $page_id )->get_data() );

		$opened = self::set_access( $page_id, true );
		self::assertSame( 200, $opened->get_status() );
		self::assertSame( array( 'open' => true ), $opened->get_data() );
		self::assertTrue( Flag::is_open( $page_id ) );
		self::assertSame( array( 'open' => true ), self::get_access( $page_id )->get_data() );

		$closed = self::set_access( $page_id, false );
		self::assertSame( array( 'open' => false ), $closed->get_data() );
		self::assertFalse( Flag::is_open( $page_id ) );

		clean_post_cache( $page_id );
		$after = get_post( $page_id );
		self::assertInstanceOf( \WP_Post::class, $after );
		self::assertSame( $before->post_modified_gmt, $after->post_modified_gmt );
		self::assertSame( $before->post_content, $after->post_content );
	}

	/**
	 * The route refuses an author and a visitor, for reading and for switching alike.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_route_refuses_everyone_below_an_editor(): void {
		$page_id = $this->a_page();

		wp_set_current_user( $this->a_user_id( 'author' ) );
		self::assertSame( 403, self::get_access( $page_id )->get_status() );
		self::assertSame( 403, self::set_access( $page_id, true )->get_status() );

		wp_set_current_user( 0 );
		self::assertSame( 401, self::get_access( $page_id )->get_status() );
		self::assertSame( 401, self::set_access( $page_id, true )->get_status() );

		self::assertFalse( Flag::is_open( $page_id ) );
	}

	/**
	 * The route reports an ID that is not a page's as not found, and flags nothing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_route_reports_a_missing_page(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A post, not a page',
			)
		);
		self::assertGreaterThan( 0, $post_id );
		$this->page_ids[] = $post_id;

		self::assertSame( 404, self::get_access( $post_id )->get_status() );
		self::assertSame( 404, self::set_access( $post_id, true )->get_status() );
		self::assertSame( '', get_post_meta( $post_id, Flag::META_KEY, true ) );
	}

	/**
	 * Core's page route neither shows the flag nor accepts it, even from an administrator: a
	 * published page does not advertise that it is open, and a save that re-sends a page's meta
	 * cannot be refused over a key the user may not write.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_flag_is_kept_out_of_core_page_route(): void {
		$page_id = $this->a_page();
		Flag::set( $page_id, true );

		$editing = new \WP_REST_Request( 'GET', '/wp/v2/pages/' . $page_id );
		$editing->set_param( 'context', 'edit' );
		$data = rest_do_request( $editing )->get_data();
		self::assertIsArray( $data );
		self::assertArrayNotHasKey( Flag::META_KEY, $data['meta'] ?? array() );

		$saving = new \WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
		$saving->set_param( 'title', 'Renamed' );
		$saving->set_param( 'meta', array( Flag::META_KEY => false ) );

		self::assertSame( 200, rest_do_request( $saving )->get_status() );
		self::assertSame( 'Renamed', get_post_field( 'post_title', $page_id, 'raw' ) );
		self::assertTrue( Flag::is_open( $page_id ) );
	}

	/**
	 * The Pages list gains the AI Canvas column for an editor and an administrator, and for
	 * nobody below.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_column_is_for_editors_and_above(): void {
		$columns = array(
			'title' => 'Title',
			'date'  => 'Date',
		);

		$for_administrator = \array_keys( apply_filters( 'manage_pages_columns', $columns ) );

		wp_set_current_user( $this->a_user_id( 'editor' ) );
		$for_editor = \array_keys( apply_filters( 'manage_pages_columns', $columns ) );

		wp_set_current_user( $this->a_user_id( 'author' ) );
		$for_author = \array_keys( apply_filters( 'manage_pages_columns', $columns ) );

		self::assertSame( array( 'title', 'a8csp-ai-canvas', 'date' ), $for_administrator );
		self::assertSame( array( 'title', 'a8csp-ai-canvas', 'date' ), $for_editor );
		self::assertSame( array( 'title', 'date' ), $for_author );
	}

	/**
	 * A page's cell carries what the list script needs to draw its switch: the page, its state,
	 * and its title. A trashed page's cell is its state as text and no more.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_cell_carries_the_switch_for_an_untrashed_page(): void {
		$page_id = $this->a_page();
		Flag::set( $page_id, true );

		$cell = self::rendered_cell( $page_id );
		self::assertStringContainsString( 'class="a8csp-ai-canvas-access"', $cell );
		self::assertStringContainsString( 'data-page-id="' . $page_id . '"', $cell );
		self::assertStringContainsString( 'data-open="1"', $cell );
		self::assertStringContainsString( 'data-title="Not a canvas page"', $cell );

		wp_trash_post( $page_id );
		self::assertSame( 'On', self::rendered_cell( $page_id ) );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Creates a published page the tools did not create, registers it for cleanup, and returns its
	 * ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private function a_page(): int {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Not a canvas page',
				'post_content' => '<!-- wp:paragraph --><p>The site owner wrote this.</p><!-- /wp:paragraph -->',
			)
		);
		self::assertGreaterThan( 0, $page_id );
		$this->page_ids[] = $page_id;

		return $page_id;
	}

	/**
	 * Creates a user with the given role, registers them for cleanup, and returns their ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $role The role.
	 *
	 * @return  int
	 */
	private function a_user_id( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'a8csp-ai-canvas-proof-' . $role,
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		self::assertIsInt( $user_id );
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Reads a page's flag through the route as the current user.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $page_id The page ID.
	 *
	 * @return  \WP_REST_Response
	 */
	private static function get_access( int $page_id ): \WP_REST_Response {
		return rest_do_request( new \WP_REST_Request( 'GET', '/a8csp-ai-canvas/v1/pages/' . $page_id . '/access' ) );
	}

	/**
	 * Switches a page's flag through the route as the current user.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int  $page_id The page ID.
	 * @param   bool $open    Whether to open the page.
	 *
	 * @return  \WP_REST_Response
	 */
	private static function set_access( int $page_id, bool $open ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/a8csp-ai-canvas/v1/pages/' . $page_id . '/access' );
		$request->set_param( 'open', $open );

		return rest_do_request( $request );
	}

	/**
	 * Renders a page's AI Canvas cell the way the Pages list does.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $page_id The page ID.
	 *
	 * @return  string
	 */
	private static function rendered_cell( int $page_id ): string {
		\ob_start();
		do_action( 'manage_pages_custom_column', 'a8csp-ai-canvas', $page_id );

		return (string) \ob_get_clean();
	}

	// endregion.
}
