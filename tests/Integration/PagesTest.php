<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use A8C\SpecialProjects\AICanvas\PageAccess\Flag;
use A8C\SpecialProjects\AICanvas\Templates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the page tools end to end, through the abilities the MCP server exposes: a created page
 * carries the flag, and a page is read, changed, or trashed only while it carries it. Each test
 * removes the pages and users it creates, since this suite runs against a persistent wp-env
 * database with no per-test transaction rollback (see tests/README.md).
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PagesTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A valid page body. Its script carries what a naive save would damage: backslashes, a bare
	 * `<`, and `&&`.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	private const string CONTENT = <<<'HTML'
<!-- wp:html {"align":"full"} -->
<style data-wp-block-html="css">
.canvas-proof { color: #14213d; }
</style>

<script data-wp-block-html="js">
(function () {
  var root = document.querySelector('.canvas-proof');
  if (root && /\d+\s*tabs/.test('2 tabs') && 1 < 2) { root.dataset.ready = "yes\n"; }
})();
</script>

<div class="canvas-proof"><h1>Proof</h1></div>
<!-- /wp:html -->
HTML;

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
	 * Signs an administrator in: the tools act as the connected user.
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
	 * Removes the capability filters the test added and deletes what it created.
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
	 * A created page carries the flag, renders on the default template as a draft, and stores the
	 * content byte for byte — backslashes included.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_create_flags_the_page_and_stores_the_content_verbatim(): void {
		$page = $this->created_page();

		self::assertSame( '1', get_post_meta( $page['id'], Flag::META_KEY, true ) );
		self::assertSame( Templates\Component::SLUGS['header-footer'], get_post_meta( $page['id'], '_wp_page_template', true ) );
		self::assertSame( 'page', get_post_type( $page['id'] ) );
		self::assertSame( 'draft', get_post_status( $page['id'] ) );
		self::assertSame( self::CONTENT, get_post_field( 'post_content', $page['id'], 'raw' ) );

		self::assertSame( 'draft', $page['status'] );
		self::assertSame( 'header-footer', $page['template'] );
		self::assertNotSame( '', $page['link'] );
		self::assertNotSame( '', $page['edit_link'] );
	}

	/**
	 * A page can be created published, on the content-only template.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_create_can_publish_on_the_content_only_template(): void {
		$page = $this->created_page(
			array(
				'template' => 'content-only',
				'status'   => 'publish',
			)
		);

		self::assertSame( 'publish', get_post_status( $page['id'] ) );
		self::assertSame( Templates\Component::SLUGS['content-only'], get_post_meta( $page['id'], '_wp_page_template', true ) );
		self::assertSame( get_permalink( $page['id'] ), $page['link'] );
	}

	/**
	 * Content that is not exactly one Custom HTML block is refused, and nothing is created.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $content The content to offer.
	 *
	 * @return  void
	 */
	#[DataProvider( 'provide_invalid_content' )]
	public function test_content_that_is_not_one_html_block_is_refused( string $content ): void {
		$pages_before = self::flagged_page_count();

		$result = self::run_tool(
			'create-page',
			array(
				'title'   => 'Refused',
				'content' => $content,
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'a8csp_ai_canvas_invalid_content', $result->get_error_code() );
		self::assertSame( $pages_before, self::flagged_page_count() );
	}

	/**
	 * Returns the content shapes the tools refuse.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array<string, array{string}>
	 */
	public static function provide_invalid_content(): array {
		return array(
			'bare markup'          => array( '<div class="canvas-proof">Hello</div>' ),
			'another block type'   => array( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' ),
			'two blocks'           => array( '<!-- wp:html {"align":"full"} --><div>One</div><!-- /wp:html --><!-- wp:html {"align":"full"} --><div>Two</div><!-- /wp:html -->' ),
			'markup after a block' => array( '<!-- wp:html {"align":"full"} --><div>One</div><!-- /wp:html --><p>Trailing</p>' ),
			'no alignment'         => array( '<!-- wp:html --><div>One</div><!-- /wp:html -->' ),
			'another alignment'    => array( '<!-- wp:html {"align":"wide"} --><div>One</div><!-- /wp:html -->' ),
			'nothing'              => array( '' ),
		);
	}

	/**
	 * A page without the flag cannot be read, changed, or trashed, and is left exactly as it was.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string               $tool  The tool to call.
	 * @param   array<string, mixed> $input The tool input, besides the page ID.
	 *
	 * @return  void
	 */
	#[DataProvider( 'provide_calls_on_a_page' )]
	public function test_a_page_without_the_flag_is_refused( string $tool, array $input ): void {
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

		$result = self::run_tool( $tool, array( 'id' => $page_id ) + $input );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'a8csp_ai_canvas_page_not_open', $result->get_error_code() );

		clean_post_cache( $page_id );
		self::assertSame( 'publish', get_post_status( $page_id ) );
		self::assertSame( 'Not a canvas page', get_post_field( 'post_title', $page_id, 'raw' ) );
		self::assertStringContainsString( 'The site owner wrote this.', get_post_field( 'post_content', $page_id, 'raw' ) );
		self::assertSame( '', get_post_meta( $page_id, '_wp_page_template', true ) );
	}

	/**
	 * Returns one call per tool that takes a page ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array<string, array{string, array<string, mixed>}>
	 */
	public static function provide_calls_on_a_page(): array {
		return array(
			'get-page'               => array( 'get-page', array() ),
			'update-page (content)'  => array( 'update-page', array( 'content' => self::CONTENT ) ),
			'update-page (title)'    => array( 'update-page', array( 'title' => 'Taken over' ) ),
			'update-page (status)'   => array( 'update-page', array( 'status' => 'draft' ) ),
			'update-page (template)' => array( 'update-page', array( 'template' => 'content-only' ) ),
			'delete-page'            => array( 'delete-page', array() ),
		);
	}

	/**
	 * A flagged page can be read back, and its content, title, status, and template changed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_flagged_page_can_be_read_and_changed(): void {
		$page = $this->created_page();

		$read = self::run_tool( 'get-page', array( 'id' => $page['id'] ) );
		self::assertIsArray( $read );
		self::assertSame( self::CONTENT, $read['content'] );

		$new_content = \str_replace( 'Proof', 'Changed', self::CONTENT );
		$updated     = self::run_tool(
			'update-page',
			array(
				'id'       => $page['id'],
				'content'  => $new_content,
				'title'    => 'Changed title',
				'status'   => 'publish',
				'template' => 'content-only',
			)
		);

		self::assertIsArray( $updated );
		self::assertSame( 'Changed title', $updated['title'] );
		self::assertSame( 'publish', $updated['status'] );
		self::assertSame( 'content-only', $updated['template'] );

		clean_post_cache( $page['id'] );
		self::assertSame( $new_content, get_post_field( 'post_content', $page['id'], 'raw' ) );
		self::assertSame( Templates\Component::SLUGS['content-only'], get_post_meta( $page['id'], '_wp_page_template', true ) );
		self::assertSame( '1', get_post_meta( $page['id'], Flag::META_KEY, true ) );
	}

	/**
	 * An update that changes only the title re-saves the stored content untouched.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_an_update_without_content_keeps_the_content(): void {
		$page = $this->created_page();

		$updated = self::run_tool(
			'update-page',
			array(
				'id'    => $page['id'],
				'title' => 'Only the title',
			)
		);

		self::assertIsArray( $updated );
		clean_post_cache( $page['id'] );
		self::assertSame( self::CONTENT, get_post_field( 'post_content', $page['id'], 'raw' ) );
	}

	/**
	 * An update with nothing to change, or with content that is not one block, is refused and
	 * leaves the page as it was.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_an_empty_or_invalid_update_is_refused(): void {
		$page = $this->created_page();

		$empty = self::run_tool( 'update-page', array( 'id' => $page['id'] ) );
		self::assertInstanceOf( \WP_Error::class, $empty );
		self::assertSame( 'a8csp_ai_canvas_nothing_to_update', $empty->get_error_code() );

		$invalid = self::run_tool(
			'update-page',
			array(
				'id'      => $page['id'],
				'content' => '<p>No block here.</p>',
			)
		);
		self::assertInstanceOf( \WP_Error::class, $invalid );
		self::assertSame( 'a8csp_ai_canvas_invalid_content', $invalid->get_error_code() );

		clean_post_cache( $page['id'] );
		self::assertSame( self::CONTENT, get_post_field( 'post_content', $page['id'], 'raw' ) );
	}

	/**
	 * Deleting a flagged page moves it to the Trash, after which the tools no longer find it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_delete_moves_a_flagged_page_to_the_trash(): void {
		$page = $this->created_page();

		$deleted = self::run_tool( 'delete-page', array( 'id' => $page['id'] ) );

		self::assertIsArray( $deleted );
		self::assertSame( 'trash', $deleted['status'] );
		self::assertSame( 'trash', get_post_status( $page['id'] ) );

		$read = self::run_tool( 'get-page', array( 'id' => $page['id'] ) );
		self::assertInstanceOf( \WP_Error::class, $read );
		self::assertSame( 'a8csp_ai_canvas_page_not_found', $read->get_error_code() );
	}

	/**
	 * A page ID that does not exist, or that belongs to something other than a page, is reported
	 * as not found.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_an_unknown_page_is_not_found(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A post, not a page',
				'meta_input' => array( Flag::META_KEY => '1' ),
			)
		);
		self::assertGreaterThan( 0, $post_id );
		$this->page_ids[] = $post_id;

		foreach ( array( $post_id, PHP_INT_MAX ) as $page_id ) {
			$result = self::run_tool( 'delete-page', array( 'id' => $page_id ) );

			self::assertInstanceOf( \WP_Error::class, $result );
			self::assertSame( 'a8csp_ai_canvas_page_not_found', $result->get_error_code() );
		}

		self::assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * The listing holds flagged pages only, and leaves trashed ones out.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_list_holds_only_flagged_untrashed_pages(): void {
		$kept    = $this->created_page();
		$trashed = $this->created_page();
		wp_trash_post( $trashed['id'] );

		$unflagged_id = wp_insert_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Not a canvas page',
			)
		);
		self::assertGreaterThan( 0, $unflagged_id );
		$this->page_ids[] = $unflagged_id;

		$listing = self::run_tool( 'list-pages' );

		self::assertIsArray( $listing );
		$listed_ids = \array_column( $listing['pages'], 'id' );
		self::assertContains( $kept['id'], $listed_ids );
		self::assertNotContains( $trashed['id'], $listed_ids );
		self::assertNotContains( $unflagged_id, $listed_ids );
	}

	/**
	 * Without `unfiltered_html`, WordPress would strip the style and script on save, so a create
	 * and an update are both refused before anything is written.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_writes_need_unfiltered_html(): void {
		$page         = $this->created_page();
		$pages_before = self::flagged_page_count();

		$this->deny_capability( 'unfiltered_html' );

		$created = self::run_tool(
			'create-page',
			array(
				'title'   => 'Refused',
				'content' => self::CONTENT,
			)
		);
		$updated = self::run_tool(
			'update-page',
			array(
				'id'    => $page['id'],
				'title' => 'Refused',
			)
		);

		foreach ( array( $created, $updated ) as $result ) {
			self::assertInstanceOf( \WP_Error::class, $result );
			self::assertSame( 'a8csp_ai_canvas_unfiltered_html_required', $result->get_error_code() );
		}

		clean_post_cache( $page['id'] );
		self::assertSame( $pages_before, self::flagged_page_count() );
		self::assertSame( self::CONTENT, get_post_field( 'post_content', $page['id'], 'raw' ) );
	}

	/**
	 * Publishing needs the capability to publish pages; saving a draft does not.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_publishing_needs_the_capability(): void {
		$this->deny_capability( 'publish_pages' );

		$published = self::run_tool(
			'create-page',
			array(
				'title'   => 'Refused',
				'content' => self::CONTENT,
				'status'  => 'publish',
			)
		);

		self::assertInstanceOf( \WP_Error::class, $published );
		self::assertSame( 'a8csp_ai_canvas_cannot_publish', $published->get_error_code() );

		self::assertSame( 'draft', $this->created_page()['status'] );
	}

	/**
	 * A user who cannot edit pages is turned away by every tool before it runs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_user_who_cannot_edit_pages_is_denied(): void {
		$page = $this->created_page();

		$author_id = wp_insert_user(
			array(
				'user_login' => 'a8csp-ai-canvas-proof-author',
				'user_pass'  => wp_generate_password(),
				'role'       => 'author',
			)
		);
		self::assertIsInt( $author_id );
		$this->user_ids[] = $author_id;
		wp_set_current_user( $author_id );

		$calls = array(
			array( 'get-instructions', null ),
			array( 'list-pages', null ),
			array( 'get-page', array( 'id' => $page['id'] ) ),
			array(
				'create-page',
				array(
					'title'   => 'Refused',
					'content' => self::CONTENT,
				),
			),
			array(
				'update-page',
				array(
					'id'    => $page['id'],
					'title' => 'Refused',
				),
			),
			array( 'delete-page', array( 'id' => $page['id'] ) ),
		);

		foreach ( $calls as list( $tool, $input ) ) {
			$result = self::run_tool( $tool, $input );

			self::assertInstanceOf( \WP_Error::class, $result, "the '{$tool}' tool must deny a user who cannot edit pages" );
			self::assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		}

		self::assertSame( 'draft', get_post_status( $page['id'] ) );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Runs a page tool the way the MCP server does: through its ability, so the input schema and
	 * the permission callback apply.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string                    $tool  The tool's short name.
	 * @param   array<string, mixed>|null $input The tool input, or null for a tool that takes none.
	 *
	 * @return  array<string, mixed>|\WP_Error
	 */
	private static function run_tool( string $tool, ?array $input = null ): array|\WP_Error {
		$ability = wp_get_ability( 'a8csp-ai-canvas/' . $tool );
		self::assertInstanceOf( \WP_Ability::class, $ability );

		$result = $ability->execute( $input );
		self::assertTrue( \is_array( $result ) || is_wp_error( $result ) );

		return $result;
	}

	/**
	 * Creates a page through the create tool, registers it for cleanup, and returns the tool's
	 * response.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $input Tool input to add to the default title and content.
	 *
	 * @return  array<string, mixed>
	 */
	private function created_page( array $input = array() ): array {
		$page = self::run_tool(
			'create-page',
			$input + array(
				'title'   => 'Proof page',
				'content' => self::CONTENT,
			)
		);

		self::assertIsArray( $page );
		self::assertIsInt( $page['id'] );
		$this->page_ids[] = $page['id'];

		return $page;
	}

	/**
	 * Counts the pages carrying the flag, in any status.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function flagged_page_count(): int {
		return \count(
			get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => Flag::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Test-only count on a small fixture.
				)
			)
		);
	}

	/**
	 * Denies one capability to every user until tearDown removes the filter.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $capability The capability to deny.
	 *
	 * @return  void
	 */
	private function deny_capability( string $capability ): void {
		$capability_filter = static fn ( array $caps, string $cap ): array => $capability === $cap ? array( 'do_not_allow' ) : $caps;

		add_filter( 'map_meta_cap', $capability_filter, 10, 2 );
		$this->capability_filters[] = $capability_filter;
	}

	// endregion.
}
