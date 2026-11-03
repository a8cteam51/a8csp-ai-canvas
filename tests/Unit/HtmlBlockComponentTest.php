<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\HtmlBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the HTML block component outside WordPress: its hook phase stages the block type
 * filter, the editor script and the editor stylesheet, and the filter adds the full-width alignment
 * to the Custom HTML block alone.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( HtmlBlock\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class HtmlBlockComponentTest extends TestCase {
	// region TESTS.

	/**
	 * The hook phase stages the block type filter, the editor script, and the stylesheet the
	 * visual editor needs in the editor's canvas.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_stages_the_filter_and_the_editor_assets(): void {
		$GLOBALS['a8csp_ai_canvas_test_hooks'] = array();

		$component = new HtmlBlock\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame( array( 'register_block_type_args', 'enqueue_block_editor_assets', 'enqueue_block_assets' ), $GLOBALS['a8csp_ai_canvas_test_hooks'] );
	}

	/**
	 * The Custom HTML block gains the full-width alignment and keeps everything else it supports.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_html_block_gains_the_full_width_alignment(): void {
		$args = ( new HtmlBlock\Component() )->support_full_alignment(
			array(
				'title'    => 'Custom HTML',
				'supports' => array(
					'className' => false,
					'html'      => false,
				),
			),
			'core/html'
		);

		self::assertSame( 'Custom HTML', $args['title'] );
		self::assertSame(
			array(
				'align'     => array( 'full' ),
				'className' => false,
				'html'      => false,
			),
			$args['supports']
		);
	}

	/**
	 * A Custom HTML block registered with no supports at all still gains the alignment.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_alignment_is_added_where_no_supports_are_declared(): void {
		$args = ( new HtmlBlock\Component() )->support_full_alignment( array(), 'core/html' );

		self::assertSame( array( 'supports' => array( 'align' => array( 'full' ) ) ), $args );
	}

	/**
	 * Every other block type is registered exactly as it was declared.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_every_other_block_is_left_alone(): void {
		$declared = array( 'supports' => array( 'anchor' => true ) );

		self::assertSame( $declared, ( new HtmlBlock\Component() )->support_full_alignment( $declared, 'core/paragraph' ) );
	}

	// endregion.
}
