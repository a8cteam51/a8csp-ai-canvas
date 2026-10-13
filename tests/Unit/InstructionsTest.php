<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\Mcp\Instructions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the authoring guide has to carry: the rule that a design reaches the site only on the
 * user's approval, what a client needs to produce content the page tools accept — the block's
 * delimiters, the attributes the editor reads the CSS and JS by, both template names, and every
 * page and media tool — and where a picture the site does not have goes.
 *
 * @since   1.0.0
 * @version 2.0.0
 */
#[CoversClass( Instructions::class )]
final class InstructionsTest extends TestCase {
	// region FIELDS AND CONSTANTS.

	/**
	 * A stand-in for the URL of the plugin's placeholder image, which the component passes to the
	 * guide.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	private const PLACEHOLDER_IMAGE_URL = 'https://example.org/wp-content/plugins/a8csp-ai-canvas/assets/images/placeholder.svg';

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
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

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
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_names_the_templates_and_the_tools(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

		foreach ( array( '`header-footer`', '`content-only`', '`create-page`', '`update-page`', '`get-page`', '`list-pages`', '`delete-page`', '`list-media`' ) as $name ) {
			self::assertStringContainsString( $name, $text );
		}
	}

	/**
	 * The guide says these tools add nothing to the site, and puts a placeholder wherever a
	 * picture is not in the Media Library: it names this site's placeholder image in place of its
	 * token, and says how to write the placeholder so a click in the editor can replace it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_puts_a_placeholder_where_the_library_has_no_picture(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

		self::assertStringContainsString( 'A picture is in the Media Library, or it is a placeholder.', $text );
		self::assertStringContainsString( 'These tools cannot add a file to the site.', $text );
		self::assertStringContainsString( "goes onto the page as this site's placeholder image: `" . self::PLACEHOLDER_IMAGE_URL . '`.', $text );
		self::assertStringNotContainsString( '{placeholder_image_url}', $text );
		self::assertStringContainsString( 'as the `src` of an `<img>`, or as the `url()` of a CSS `background-image`', $text );
		self::assertStringContainsString( 'Before you publish a page that still has placeholders', $text );
		self::assertStringNotContainsString( 'upload-media', $text );
		self::assertStringNotContainsString( 'base64', $text );
	}

	/**
	 * The guide has the client hand the pictures over with the page: one archive of the files the
	 * placeholders stand for, and which file belongs in which.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function test_the_guide_hands_the_pictures_over_with_the_page(): void {
		$text = Instructions::text( self::PLACEHOLDER_IMAGE_URL );

		self::assertStringContainsString( 'give the user one `.zip` of the pictures they stand for', $text );
		self::assertStringContainsString( 'unchanged and under their own names', $text );
		self::assertStringContainsString( 'Tell the user which file belongs in which placeholder.', $text );
	}

	/**
	 * The pointer the write tools carry states the block's shape, says where a picture the site
	 * does not have goes, and sends the client to the full guide.
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
		self::assertStringContainsString( 'goes in as the placeholder image named in get-instructions', $pointer );
		self::assertStringContainsString( 'Call get-instructions', $pointer );
	}

	// endregion.
}
