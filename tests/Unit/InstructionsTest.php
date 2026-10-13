<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\Mcp\Instructions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the authoring guide has to carry: the rule that a design reaches the site only on the
 * user's approval, what a client needs to produce content the page tools accept — the block's
 * delimiters, the attributes the editor reads the CSS and JS by, both template names, and every
 * page and media tool — and where a file with no public URL goes.
 *
 * @since   1.0.0
 * @version 1.0.1
 */
#[CoversClass( Instructions::class )]
final class InstructionsTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A stand-in for the site's own upload screen, which the component passes to the guide.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @var     string
	 */
	private const MEDIA_UPLOAD_LINK = 'https://example.org/wp-admin/media-new.php';

	// endregion.

	// region TESTS.

	/**
	 * The guide tells the client to design first and write to the site only on approval, and says
	 * so ahead of everything about how to write: it names the three tools that write.
	 *
	 * @since   1.0.0
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_guide_puts_approval_ahead_of_the_format(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		$rule   = \strpos( $text, 'Do not write to the site until the user approves.' );
		$format = \strpos( $text, '## The content format' );

		self::assertIsInt( $rule );
		self::assertIsInt( $format );
		self::assertLessThan( $format, $rule );
		self::assertStringContainsString( 'Call `create-page`, `update-page` and `upload-media` only after the user has seen the design, approved it, and asked for it to go to the site.', $text );
	}

	/**
	 * The sentence the writing tools carry in their descriptions states the same rule.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_approval_sentence_states_the_rule(): void {
		self::assertStringContainsString( 'only after the user has approved the design', Instructions::approval() );
	}

	/**
	 * The guide shows the block in the shape WordPress serializes it: the delimiters, the opening
	 * one carrying the full-width alignment, around a style element, a script element, and the
	 * markup, in that order.
	 *
	 * @since   1.0.0
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_guide_shows_the_block_in_serialized_order(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		$open   = \strpos( $text, '<!-- wp:html {"align":"full"} -->' );
		$style  = \strpos( $text, '<style data-wp-block-html="css">' );
		$script = \strpos( $text, '<script data-wp-block-html="js">' );
		$close  = \strpos( $text, '<!-- /wp:html -->' );

		self::assertIsInt( $open );
		self::assertIsInt( $style );
		self::assertIsInt( $script );
		self::assertIsInt( $close );
		self::assertLessThan( $style, $open );
		self::assertLessThan( $script, $style );
		self::assertLessThan( $close, $script );
	}

	/**
	 * The guide names both templates and every page and media tool.
	 *
	 * @since   1.0.0
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_guide_names_the_templates_and_the_tools(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		foreach ( array( '`header-footer`', '`content-only`', '`create-page`', '`update-page`', '`get-page`', '`list-pages`', '`delete-page`', '`list-media`', '`upload-media`' ) as $name ) {
			self::assertStringContainsString( $name, $text );
		}
	}

	/**
	 * The guide sends a file with no public URL to the user: it says base64 only suits a small
	 * file, gives the link to this site's upload screen in place of its placeholder, and names the
	 * tool that finds the file afterwards.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_guide_sends_a_file_with_no_public_url_to_the_user(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		self::assertStringContainsString( 'A file with no public URL is the user\'s to upload.', $text );
		self::assertStringContainsString( 'a file of a few kilobytes', $text );
		self::assertStringContainsString( 'ask the user to add it to the Media Library at ' . self::MEDIA_UPLOAD_LINK . ', then find it with `list-media`', $text );
		self::assertStringContainsString( 'do not promise to upload it yourself', $text );
		self::assertStringNotContainsString( '{media_upload_link}', $text );
	}

	/**
	 * The sentence the upload tool carries in its description states the same rule.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_local_files_sentence_states_the_rule(): void {
		$sentence = Instructions::local_files();

		self::assertStringContainsString( 'A file with no public URL', $sentence );
		self::assertStringContainsString( 'a few kilobytes', $sentence );
		self::assertStringContainsString( 'ask the user to add it to the Media Library', $sentence );
		self::assertStringContainsString( 'list-media', $sentence );
	}

	/**
	 * The pointer the write tools carry states the block's shape and sends the client to the
	 * full guide.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_pointer_states_the_shape_and_names_the_guide_tool(): void {
		$pointer = Instructions::pointer();

		self::assertStringContainsString( '<!-- wp:html {"align":"full"} -->', $pointer );
		self::assertStringContainsString( 'data-wp-block-html="css"', $pointer );
		self::assertStringContainsString( 'data-wp-block-html="js"', $pointer );
		self::assertStringContainsString( 'get-instructions', $pointer );
	}

	// endregion.
}
