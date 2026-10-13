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
 * @version 1.1.0
 */
#[CoversClass( Instructions::class )]
final class InstructionsTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A stand-in for the URL of the plugin's placeholder image, which the component passes to the
	 * guide.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @var     string
	 */
	private const PLACEHOLDER_IMAGE_URL = 'https://example.org/wp-content/plugins/a8csp-ai-canvas/assets/images/placeholder.svg';

	// endregion.

	// region TESTS.

	/**
	 * The guide tells the client to design first and write to the site only on approval, and says
	 * so ahead of everything about how to write: it names the three tools that write.
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 *
	 * @return  void
	 */
	public function test_the_guide_puts_approval_ahead_of_the_format(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

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
	 * @version 1.1.0
	 *
	 * @return  void
	 */
	public function test_the_guide_shows_the_block_in_serialized_order(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

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
	 * @version 1.1.0
	 *
	 * @return  void
	 */
	public function test_the_guide_names_the_templates_and_the_tools(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

		foreach ( array( '`header-footer`', '`content-only`', '`create-page`', '`update-page`', '`get-page`', '`list-pages`', '`delete-page`', '`list-media`', '`upload-media`' ) as $name ) {
			self::assertStringContainsString( $name, $text );
		}
	}

	/**
	 * The guide puts a placeholder where a file has no public URL: it names this site's
	 * placeholder image in place of its token, says base64 only suits a small file, and says how
	 * to write the placeholder so a click in the editor can replace it.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @return  void
	 */
	public function test_the_guide_puts_a_placeholder_where_a_file_has_no_public_url(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

		self::assertStringContainsString( 'A file with no public URL goes in as a placeholder.', $text );
		self::assertStringContainsString( "put this site's placeholder image in its place: `" . self::PLACEHOLDER_IMAGE_URL . '`.', $text );
		self::assertStringNotContainsString( '{placeholder_image_url}', $text );
		self::assertStringContainsString( 'unless it is a few kilobytes', $text );
		self::assertStringContainsString( 'as the `src` of an `<img>`, or as the `url()` of a CSS `background-image`', $text );
		self::assertStringContainsString( 'tell the user which placeholders it has and which file belongs in each', $text );
		self::assertStringContainsString( 'Before you publish a page that still has placeholders', $text );
	}

	/**
	 * The sentence the upload tool carries in its description states the same rule, and sends the
	 * client to the guide for the placeholder.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @return  void
	 */
	public function test_the_local_files_sentence_states_the_rule(): void {
		$sentence = Instructions::local_files();

		self::assertStringContainsString( 'A file with no public URL', $sentence );
		self::assertStringContainsString( 'a few kilobytes', $sentence );
		self::assertStringContainsString( 'placeholder image', $sentence );
		self::assertStringContainsString( 'get-instructions', $sentence );
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
