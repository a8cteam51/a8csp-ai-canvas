<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\HtmlBlock;

use A8C\SpecialProjects\AICanvas\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the HTML Block feature, which is what the editor adds to core's Custom HTML block.
 *
 * It lets the block be aligned to the full width, which core's own does not offer, and has the
 * editor show a block aligned that way across its full width in whatever layout the block sits.
 * The block saves no wrapper element, so the alignment changes nothing on the front end, where the
 * page templates already give the content the full width. It is for the editor, which would
 * otherwise show an AI Canvas page squeezed into the theme's content column.
 *
 * And it makes the block's content editable by pointing and clicking: text is typed over where it
 * stands, and images, links and background images are changed from a toolbar beside them. That is
 * all in the editor script. Each change is written into the block's HTML, so what is saved is still
 * one Custom HTML block, and nothing here runs on the front end.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Component extends AbstractComponent {
	// region METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'register_block_type_args', array( $this, 'support_full_alignment' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_script' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_style' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the full-width alignment to what the Custom HTML block supports, which also registers
	 * the block's `align` attribute and puts the option in its toolbar wherever the layout around
	 * the block offers alignments.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, mixed> $args       The arguments the block type is being registered with.
	 * @param   string               $block_type The block type's name.
	 *
	 * @return  array<string, mixed>
	 */
	public function support_full_alignment( array $args, string $block_type ): array {
		if ( 'core/html' === $block_type ) {
			$supports         = $args['supports'] ?? array();
			$args['supports'] = array( 'align' => array( 'full' ) ) + ( \is_array( $supports ) ? $supports : array() );
		}

		return $args;
	}

	/**
	 * Enqueues the block's editor script, with its translations. The script shows a full-width
	 * Custom HTML block at the full width of the editor: left to core, the editor honours an
	 * alignment only inside a constrained layout, and the page templates give the content the flow
	 * layout. It is also the whole of the visual editor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_editor_script(): void {
		$asset_meta = a8csp_ai_canvas_get_asset_meta( 'assets/js/build/html-block.js' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$handle = a8csp_ai_canvas_get_plugin_slug() . '-html-block';

		wp_enqueue_script( $handle, \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/js/build/html-block.js', $asset_meta['dependencies'], $asset_meta['version'], true );
		wp_set_script_translations( $handle, 'a8csp-ai-canvas' );
	}

	/**
	 * Enqueues the visual editor's stylesheet, in the editor only.
	 *
	 * The hook is the one whose styles WordPress loads both into the editor's canvas, which is an
	 * iframe, and into the editor around it. The stylesheet needs both: the canvas for the content
	 * being edited and the boxes drawn over it, the editor for the toolbar. The hook also fires on
	 * the front end, where the stylesheet has nothing to style.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_editor_style(): void {
		if ( ! is_admin() ) {
			return;
		}

		$asset_meta = a8csp_ai_canvas_get_asset_meta( 'assets/js/build/html-block.css' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$handle = a8csp_ai_canvas_get_plugin_slug() . '-html-block';

		wp_enqueue_style( $handle, \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/js/build/html-block.css', array(), $asset_meta['version'] );
		wp_style_add_data( $handle, 'rtl', 'replace' );
	}

	// endregion
}
