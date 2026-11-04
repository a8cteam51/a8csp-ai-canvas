<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\WebMcp;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\Templates;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the WebMCP feature: in the page editor, registers the tools an AI agent in the user's
 * browser calls to build the page being edited, through the browser's WebMCP API.
 *
 * The tools are all in the editor script. They read and change the page in the editor, as the
 * signed-in user, and nothing reaches the site's visitors until that user publishes it. In a
 * browser without WebMCP the script registers nothing.
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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_script' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Enqueues the tools script in the block editor of a page, handing it the page templates the
	 * agent can choose from. The hook also fires in the site editor and in the editor of every
	 * other post type, where the tools have no page to work on.
	 *
	 * The script has no translations: everything it says is addressed to the agent.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_editor_script(): void {
		$screen = get_current_screen();
		if ( null === $screen || 'post' !== $screen->base || 'page' !== $screen->post_type || ! $screen->is_block_editor() ) {
			return;
		}

		$asset_meta = a8csp_ai_canvas_get_asset_meta( 'assets/js/build/webmcp.js' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$handle = a8csp_ai_canvas_get_plugin_slug() . '-webmcp';

		wp_enqueue_script( $handle, \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/js/build/webmcp.js', $asset_meta['dependencies'], $asset_meta['version'], true );
		wp_add_inline_script( $handle, 'window.a8csp_ai_canvas = ' . wp_json_encode( array( 'templates' => Templates\Component::SLUGS ) ) . ';', 'before' );
	}

	// endregion
}
