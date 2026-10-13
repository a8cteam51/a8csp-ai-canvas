<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the media tool end to end, through the ability the MCP server exposes: a file in the
 * Media Library is found by a word in its title and comes back with its URL, its details and its
 * smaller sizes, and a user who cannot browse the library is turned away. Each test deletes the
 * attachments it creates, since this suite runs against a persistent wp-env database with no
 * per-test transaction rollback (see tests/README.md).
 *
 * @since   1.0.0
 * @version 2.0.0
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
	 * A file in the library comes back with its URL, type, title, alternative text and dimensions.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_list_returns_a_file_with_its_url_and_details(): void {
		$attachment_id = $this->attachment( self::pixel(), 'a8csp-ai-canvas-proof.png', 'Canvasproofword pixel' );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'A single pixel' );

		$media = $this->listed( $attachment_id, 'Canvasproofword' );

		self::assertSame( 'image/png', $media['mime'] );
		self::assertSame( 'Canvasproofword pixel', $media['title'] );
		self::assertSame( 'A single pixel', $media['alt'] );
		self::assertSame( 1, $media['width'] );
		self::assertSame( 1, $media['height'] );
		self::assertIsString( $media['url'] );
		self::assertStringEndsWith( '.png', $media['url'] );
		self::assertSame( wp_get_attachment_url( $attachment_id ), $media['url'] );
	}

	/**
	 * A large image comes back with the smaller sizes WordPress generated, each with its own URL
	 * and dimensions, so a client can reference one that fits.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_a_listed_image_comes_back_with_its_smaller_sizes(): void {
		if ( ! \function_exists( 'imagecreatetruecolor' ) ) {
			self::markTestSkipped( 'This proof draws its image with GD, which this PHP build lacks.' );
		}

		$image = \imagecreatetruecolor( 1600, 900 );
		self::assertNotFalse( $image );
		\ob_start();
		\imagejpeg( $image );
		$jpeg = (string) \ob_get_clean();

		$attachment_id = $this->attachment( $jpeg, 'a8csp-ai-canvas-proof-large.jpg', 'Canvasproofword large' );
		$media         = $this->listed( $attachment_id, 'Canvasproofword' );

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
	 * The listing finds files by a word in their titles, and honors the limit.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_list_finds_files_by_a_word_in_the_title(): void {
		$first  = $this->attachment( self::pixel(), 'a8csp-ai-canvas-proof-one.png', 'Canvasproofword one' );
		$second = $this->attachment( self::pixel(), 'a8csp-ai-canvas-proof-two.png', 'Canvasproofword two' );

		$found = self::run_tool( 'list-media', array( 'search' => 'Canvasproofword' ) );
		self::assertIsArray( $found );
		$found_ids = \array_column( $found['media'], 'id' );
		self::assertContains( $first, $found_ids );
		self::assertContains( $second, $found_ids );

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
	 * A user who cannot upload files cannot browse the library in wp-admin, and is turned away
	 * here before the tool runs.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_a_user_who_cannot_upload_files_is_denied(): void {
		$this->filter(
			'map_meta_cap',
			static fn ( array $caps, string $cap ): array => 'upload_files' === $cap ? array( 'do_not_allow' ) : $caps,
			2
		);

		$result = self::run_tool( 'list-media', array() );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'ability_invalid_permissions', $result->get_error_code() );
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
	 * Returns the contents of the one-pixel PNG.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	private static function pixel(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the test image this file carries as text.
		return (string) \base64_decode( self::PIXEL, true );
	}

	/**
	 * Adds a file to the Media Library the way WordPress itself does, since no tool does, registers
	 * the attachment for cleanup, and returns its ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $contents The file's contents.
	 * @param   string $filename The name to store it under.
	 * @param   string $title    The title to give it.
	 *
	 * @return  int
	 */
	private function attachment( string $contents, string $filename, string $title ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temporary_file = wp_tempnam( $filename );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fills the temporary file wp_tempnam() just created, as core's own upload handling does.
		self::assertNotFalse( \file_put_contents( $temporary_file, $contents ) );

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $temporary_file,
			),
			0,
			$title
		);
		self::assertIsInt( $attachment_id );
		$this->attachment_ids[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Searches the library through the tool and returns its entry for the given attachment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $attachment_id The attachment to find.
	 * @param   string $search        The words to search for.
	 *
	 * @return  array<string, mixed>
	 */
	private function listed( int $attachment_id, string $search ): array {
		$found = self::run_tool( 'list-media', array( 'search' => $search ) );
		self::assertIsArray( $found );

		foreach ( $found['media'] as $media ) {
			if ( $attachment_id === $media['id'] ) {
				return $media;
			}
		}

		self::fail( "list-media must find attachment {$attachment_id} by '{$search}'" );
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
