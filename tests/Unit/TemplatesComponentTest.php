<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\Templates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the templates component outside WordPress: it registers its hooks, and its texturize
 * switch turns texturize off only for a page rendered on one of the two templates.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( Templates\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class TemplatesComponentTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Loads the page-query stand-ins the texturize switch reads.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-page-stubs.php';
	}

	/**
	 * Starts each test with an empty hook-registration ledger and a request for a page on the
	 * header-and-footer template.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_ai_canvas_test_hooks']         = array();
		$GLOBALS['a8csp_ai_canvas_test_is_page']       = true;
		$GLOBALS['a8csp_ai_canvas_test_page_template'] = Templates\Component::SLUGS['header-footer'];
	}

	// endregion.

	// region TESTS.

	/**
	 * The hook phase stages the block templates and the two filters a page on either template
	 * needs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_registers_the_templates_and_their_filters(): void {
		$component = new Templates\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame( array( 'init', 'get_block_templates', 'run_wptexturize' ), $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	/**
	 * Texturize is switched off for a page on either template.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_texturize_is_skipped_on_both_templates(): void {
		$component = new Templates\Component();

		self::assertFalse( $component->skip_texturize( true ) );

		$GLOBALS['a8csp_ai_canvas_test_page_template'] = Templates\Component::SLUGS['content-only'];
		self::assertFalse( $component->skip_texturize( true ) );
	}

	/**
	 * Texturize keeps running for a page on any other template, and for a request that is not for
	 * a page at all.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_texturize_runs_everywhere_else(): void {
		$component = new Templates\Component();

		$GLOBALS['a8csp_ai_canvas_test_page_template'] = 'page-no-title';
		self::assertTrue( $component->skip_texturize( true ) );

		$GLOBALS['a8csp_ai_canvas_test_page_template'] = '';
		self::assertTrue( $component->skip_texturize( true ) );

		$GLOBALS['a8csp_ai_canvas_test_page_template'] = Templates\Component::SLUGS['header-footer'];
		$GLOBALS['a8csp_ai_canvas_test_is_page']       = false;
		self::assertTrue( $component->skip_texturize( true ) );
	}

	/**
	 * A request something else already switched texturize off for stays off.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_texturize_already_off_stays_off(): void {
		$GLOBALS['a8csp_ai_canvas_test_page_template'] = 'page-no-title';

		self::assertFalse( ( new Templates\Component() )->skip_texturize( false ) );
	}

	/**
	 * Templates keyed by name come back as a list, in the same order.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_block_templates_are_reindexed_as_a_list(): void {
		$first  = new \stdClass();
		$second = new \stdClass();

		$templates = ( new Templates\Component() )->reindex_block_templates(
			array(
				'a8csp-ai-canvas//ai-canvas-header-footer' => $first,
				0                                          => $second,
			)
		);

		self::assertSame( array( $first, $second ), $templates );
	}

	// endregion.
}
