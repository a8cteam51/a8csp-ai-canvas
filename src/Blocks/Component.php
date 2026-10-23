<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Blocks;

use A8C\SpecialProjects\AICanvasWebMCP\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the Blocks feature: registers the plugin's blocks and the block-editor assets.
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
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_script' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the plugin's blocks from the build's metadata manifest.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_blocks(): void {
		wp_register_block_types_from_metadata_collection( \constant( 'A8CSP_AI_WEBMCP_DIR_PATH' ) . 'blocks/build', \constant( 'A8CSP_AI_WEBMCP_DIR_PATH' ) . 'blocks/build/blocks-manifest.php' );
	}

	/**
	 * Registers and enqueues the plugin's block-editor script and its translations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function enqueue_editor_script(): void {
		$asset_meta = a8csp_ai_webmcp_get_asset_meta( 'assets/js/build/editor.js' );
		if ( \is_null( $asset_meta ) ) {
			return;
		}

		$plugin_slug = a8csp_ai_webmcp_get_plugin_slug();
		wp_register_script( "$plugin_slug-editor", \constant( 'A8CSP_AI_WEBMCP_DIR_URL' ) . 'assets/js/build/editor.js', $asset_meta['dependencies'], $asset_meta['version'], false );
		wp_enqueue_script( "$plugin_slug-editor" );
		wp_set_script_translations( "$plugin_slug-editor", 'a8csp-ai-canvas-webmcp' );
	}

	// endregion
}
