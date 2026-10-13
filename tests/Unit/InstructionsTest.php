<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\Mcp\Instructions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the authoring guide has to carry: the rule that a design reaches the site only on the
 * user's approval, what a client needs to produce content the page tools accept — the block's
 * delimiters, the attributes the editor reads the CSS and JS by, both template names, and every
 * page and media tool — and where a page's pictures come from.
 *
 * @since   1.0.0
 * @version 2.0.0
 */
#[CoversClass( Instructions::class )]
final class InstructionsTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A stand-in for the site's own upload screen, which the component passes to the guide.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	private const MEDIA_UPLOAD_LINK = 'https://example.org/wp-admin/media-new.php';

	// endregion.

	// region TESTS.

	/**
	 * The guide tells the client to design first and write to the site only on approval, and says
	 * so ahead of everything about how to write: it names the two tools that write.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
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
		self::assertStringContainsString( 'Call `create-page` and `update-page` only after the user has seen the design, approved it, and asked for it to go to the site.', $text );
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
	 * @version 2.0.0
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
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_names_the_templates_and_the_tools(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		foreach ( array( '`header-footer`', '`content-only`', '`create-page`', '`update-page`', '`get-page`', '`list-pages`', '`delete-page`', '`list-media`' ) as $name ) {
			self::assertStringContainsString( $name, $text );
		}
	}

	/**
	 * The guide says these tools add nothing to the site and that every picture on a page is a
	 * Media Library file, and has the client ask for a picture to be put there first, at this
	 * site's upload screen, named in place of its token.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_takes_every_picture_from_the_media_library(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		self::assertStringContainsString( "Every picture on a page is a file in this site's Media Library.", $text );
		self::assertStringContainsString( 'These tools cannot add a file to the site.', $text );
		self::assertStringContainsString( 'ask them to add it to the Media Library at `' . self::MEDIA_UPLOAD_LINK . '`, and then to tell you, or to paste its URL', $text );
		self::assertStringNotContainsString( '{media_upload_link}', $text );
		self::assertStringContainsString( 'while the design is still being worked on', $text );
		self::assertStringNotContainsString( 'upload-media', $text );
		self::assertStringNotContainsString( 'base64', $text );
	}

	/**
	 * The guide tells the client that a copy it downloaded to design with is not what the page
	 * references: the page gets the Media Library URL back, and never a file of the client's own.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_sends_the_media_library_url_and_not_the_copy(): void {
		$text = Instructions::text( self::MEDIA_UPLOAD_LINK );

		self::assertStringContainsString( "Design with a copy, send the original's URL.", $text );
		self::assertStringContainsString( "put that file's Media Library URL back in its place", $text );
		self::assertStringContainsString( 'still use its Media Library URL in the page', $text );
		self::assertStringContainsString( 'Never send a page that points at a file of your own.', $text );
		self::assertStringContainsString( 'leave that picture out and tell them which is missing', $text );
	}

	/**
	 * The pointer the write tools carry states the block's shape, says where the content's
	 * pictures come from, and sends the client to the full guide.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_pointer_states_the_shape_and_names_the_guide_tool(): void {
		$pointer = Instructions::pointer();

		self::assertStringContainsString( '<!-- wp:html {"align":"full"} -->', $pointer );
		self::assertStringContainsString( 'data-wp-block-html="css"', $pointer );
		self::assertStringContainsString( 'data-wp-block-html="js"', $pointer );
		self::assertStringContainsString( "every picture in the content is the URL of a file in this site's Media Library", $pointer );
		self::assertStringContainsString( 'Call get-instructions', $pointer );
	}

	// endregion.
}
