<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the media tools end to end, through the abilities the MCP server exposes: a file sent
 * as base64 lands in the Media Library and can be found again, and WordPress's own limits on size
 * and file type still apply. Each test deletes the attachments it creates, since this suite runs
 * against a persistent wp-env database with no per-test transaction rollback (see
 * tests/README.md).
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class MediaTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A one-pixel PNG, base64-encoded.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	/**
	 * The IDs of the attachments a test created, for tearDown to delete.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<int>
	 */
	private array $attachment_ids = array();

	/**
	 * The filters a test added, as hook name and callback pairs, for tearDown to remove.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<array{string, \Closure}>
	 */
	private array $filters = array();

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
	 * Removes the filters the test added and deletes the attachments it created, files included.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		foreach ( $this->filters as list( $hook_name, $callback ) ) {
			remove_filter( $hook_name, $callback );
		}

		foreach ( $this->attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * A file sent as base64 becomes an attachment, stored under the given name with the given
	 * title and alternative text, and comes back with its URL and dimensions.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_upload_adds_a_base64_file_to_the_media_library(): void {
		$media = $this->uploaded_media(
			array(
				'base64'   => self::PIXEL,
				'filename' => 'a8csp-ai-canvas-proof.png',
				'title'    => 'AI Canvas proof pixel',
				'alt'      => 'A single pixel',
			)
		);

		self::assertSame( 'attachment', get_post_type( $media['id'] ) );
		self::assertSame( 'image/png', $media['mime'] );
		self::assertSame( 'AI Canvas proof pixel', $media['title'] );
		self::assertSame( 'A single pixel', $media['alt'] );
		self::assertSame( 1, $media['width'] );
		self::assertSame( 1, $media['height'] );
		self::assertIsString( $media['url'] );
		self::assertStringEndsWith( '.png', $media['url'] );
		self::assertSame( wp_get_attachment_url( $media['id'] ), $media['url'] );
		self::assertFileExists( (string) get_attached_file( $media['id'] ) );
	}

	/**
	 * A large image comes back with the smaller sizes WordPress generated, each with its own URL
	 * and dimensions, so a client can reference one that fits.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_an_uploaded_image_comes_back_with_its_smaller_sizes(): void {
		if ( ! \function_exists( 'imagecreatetruecolor' ) ) {
			self::markTestSkipped( 'This proof draws its image with GD, which this PHP build lacks.' );
		}

		$image = \imagecreatetruecolor( 1600, 900 );
		self::assertNotFalse( $image );
		\ob_start();
		\imagejpeg( $image );
		$jpeg = (string) \ob_get_clean();

		$media = $this->uploaded_media(
			array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes the test image the way an MCP client sends a file.
				'base64'   => \base64_encode( $jpeg ),
				'filename' => 'a8csp-ai-canvas-proof-large.jpg',
			)
		);

		self::assertSame( 1600, $media['width'] );
		self::assertSame( 900, $media['height'] );
		self::assertNotEmpty( $media['sizes'] );

		foreach ( $media['sizes'] as $size ) {
			self::assertLessThan( 1600, $size['width'] );
			self::assertNotSame( $media['url'], $size['url'] );
			self::assertStringStartsWith( 'http', $size['url'] );
		}
	}

	/**
	 * The listing finds an uploaded file by a word in its title, and honors the limit.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_list_finds_an_uploaded_file_by_its_title(): void {
		$first  = $this->uploaded_media(
			array(
				'base64'   => self::PIXEL,
				'filename' => 'a8csp-ai-canvas-proof-one.png',
				'title'    => 'Canvasproofword one',
			)
		);
		$second = $this->uploaded_media(
			array(
				'base64'   => self::PIXEL,
				'filename' => 'a8csp-ai-canvas-proof-two.png',
				'title'    => 'Canvasproofword two',
			)
		);

		$found = self::run_tool( 'list-media', array( 'search' => 'Canvasproofword' ) );
		self::assertIsArray( $found );
		$found_ids = \array_column( $found['media'], 'id' );
		self::assertContains( $first['id'], $found_ids );
		self::assertContains( $second['id'], $found_ids );

		$limited = self::run_tool(
			'list-media',
			array(
				'search' => 'Canvasproofword',
				'limit'  => 1,
			)
		);
		self::assertIsArray( $limited );
		self::assertCount( 1, $limited['media'] );
	}

	/**
	 * Input that names no source, base64 without a filename, and base64 that does not decode are
	 * all refused, and nothing is added to the library.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_input_without_a_usable_source_is_refused(): void {
		$attachments_before = self::attachment_count();

		$inputs = array(
			array( 'title' => 'No source' ),
			array( 'base64' => self::PIXEL ),
			array(
				'base64'   => 'This is not base64!',
				'filename' => 'a8csp-ai-canvas-proof.png',
			),
		);

		foreach ( $inputs as $input ) {
			$result = self::run_tool( 'upload-media', $input );

			self::assertInstanceOf( \WP_Error::class, $result );
			self::assertSame( 'a8csp_ai_canvas_invalid_media', $result->get_error_code() );
		}

		self::assertSame( $attachments_before, self::attachment_count() );
	}

	/**
	 * A file over the site's upload limit is refused before it is written anywhere.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_file_over_the_upload_limit_is_refused(): void {
		$attachments_before = self::attachment_count();

		$this->filter( 'upload_size_limit', static fn (): int => 10 );

		$result = self::run_tool(
			'upload-media',
			array(
				'base64'   => self::PIXEL,
				'filename' => 'a8csp-ai-canvas-proof.png',
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'a8csp_ai_canvas_media_too_large', $result->get_error_code() );
		self::assertSame( $attachments_before, self::attachment_count() );
	}

	/**
	 * WordPress's own file-type rules apply: a PHP file is refused, and nothing is added.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_disallowed_file_type_is_refused(): void {
		$attachments_before = self::attachment_count();

		$result = self::run_tool(
			'upload-media',
			array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes the test file the way an MCP client sends a file.
				'base64'   => \base64_encode( '<?php echo "proof";' ),
				'filename' => 'a8csp-ai-canvas-proof.php',
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( $attachments_before, self::attachment_count() );
	}

	/**
	 * A user who cannot upload files is turned away by both tools before either runs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_a_user_who_cannot_upload_files_is_denied(): void {
		$this->filter(
			'map_meta_cap',
			static fn ( array $caps, string $cap ): array => 'upload_files' === $cap ? array( 'do_not_allow' ) : $caps,
			2
		);

		$calls = array(
			array( 'list-media', array() ),
			array(
				'upload-media',
				array(
					'base64'   => self::PIXEL,
					'filename' => 'a8csp-ai-canvas-proof.png',
				),
			),
		);

		foreach ( $calls as list( $tool, $input ) ) {
			$result = self::run_tool( $tool, $input );

			self::assertInstanceOf( \WP_Error::class, $result, "the '{$tool}' tool must deny a user who cannot upload files" );
			self::assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		}
	}

	// endregion.

	// region HELPERS.

	/**
	 * Runs a media tool the way the MCP server does: through its ability, so the input schema and
	 * the permission callback apply.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string               $tool  The tool's short name.
	 * @param   array<string, mixed> $input The tool input.
	 *
	 * @return  array<string, mixed>|\WP_Error
	 */
	private static function run_tool( string $tool, array $input ): array|\WP_Error {
		$ability = wp_get_ability( 'a8csp-ai-canvas/' . $tool );
		self::assertInstanceOf( \WP_Ability::class, $ability );

		$result = $ability->execute( $input );
		self::assertTrue( \is_array( $result ) || is_wp_error( $result ) );

		return $result;
	}

	/**
	 * Uploads a file through the upload tool, registers the attachment for cleanup, and returns
	 * the tool's response.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $input The tool input.
	 *
	 * @return  array<string, mixed>
	 */
	private function uploaded_media( array $input ): array {
		$media = self::run_tool( 'upload-media', $input );

		self::assertIsArray( $media );
		self::assertIsInt( $media['id'] );
		$this->attachment_ids[] = $media['id'];

		return $media;
	}

	/**
	 * Counts the attachments in the Media Library.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function attachment_count(): int {
		return \array_sum( (array) wp_count_posts( 'attachment' ) );
	}

	/**
	 * Adds a filter that tearDown removes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string   $hook_name     The filter's hook name.
	 * @param   \Closure $callback      The filter callback.
	 * @param   int      $accepted_args The number of arguments the callback accepts.
	 *
	 * @return  void
	 */
	private function filter( string $hook_name, \Closure $callback, int $accepted_args = 1 ): void {
		add_filter( $hook_name, $callback, 10, $accepted_args );
		$this->filters[] = array( $hook_name, $callback );
	}

	// endregion.
}
