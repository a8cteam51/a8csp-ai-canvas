<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Templates;

use A8C\SpecialProjects\AICanvas\AbstractComponent;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the Templates feature: provides the two page templates an AI Canvas page renders on,
 * so both are offered in the page editor's Template panel, and keeps WordPress from rewriting the
 * inline scripts of a page rendered on either.
 *
 * Both are block templates, so they need a block theme: WordPress renders no block template under
 * any other.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Component extends AbstractComponent {
	// region FIELDS AND CONSTANTS

	/**
	 * The template slugs, keyed by the short name the MCP page tools accept. The slug is what
	 * WordPress stores in a page's `_wp_page_template` meta.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array SLUGS = array(
		'header-footer' => 'ai-canvas-header-footer',
		'content-only'  => 'ai-canvas-content-only',
	);

	// endregion

	// region METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_templates' ) );
		add_filter( 'get_block_templates', array( $this, 'reindex_block_templates' ) );
		add_filter( 'run_wptexturize', array( $this, 'skip_texturize' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers both templates as block templates. Neither prints the page title, and the content
	 * area uses the flow layout, so a page's content spans the full width instead of the theme's
	 * content column.
	 *
	 * The block gap is zeroed at both levels. Without that, the theme's gap opens a strip of page
	 * background between the header, the content and the footer, and another above the content's
	 * root element, which follows the block's style and script elements.
	 *
	 * The header and footer are `core/template-part` blocks rather than hard-coded markup, so the
	 * active theme's own parts and wrapper elements are what render.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_templates(): void {
		$plugin_slug = a8csp_ai_canvas_get_plugin_slug();

		register_block_template(
			$plugin_slug . '//' . self::SLUGS['header-footer'],
			array(
				'title'       => __( 'AI Canvas: Header, Content, Footer', 'a8csp-ai-canvas' ),
				'description' => __( 'The site header, the page content at full width, and the site footer. No page title.', 'a8csp-ai-canvas' ),
				'post_types'  => array( 'page' ),
				'content'     => '<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} --><div class="wp-block-group">'
					. '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
					. '<!-- wp:post-content {"tagName":"main","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} /-->'
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'
					. '</div><!-- /wp:group -->',
			)
		);

		register_block_template(
			$plugin_slug . '//' . self::SLUGS['content-only'],
			array(
				'title'       => __( 'AI Canvas: Content Only', 'a8csp-ai-canvas' ),
				'description' => __( 'Only the page content at full width. No header, footer, or page title.', 'a8csp-ai-canvas' ),
				'post_types'  => array( 'page' ),
				'content'     => '<!-- wp:post-content {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} /-->',
			)
		);
	}

	/**
	 * Returns the queried block templates as a list.
	 *
	 * Core returns plugin-registered templates keyed by name but reads the result as a list: the
	 * editor's `wp_get_post_content_block_attributes()` takes `$templates[0]`. Left keyed, opening a
	 * page on either template raises PHP warnings and the editor ignores the template's layout.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int|string, mixed> $templates The queried block templates.
	 *
	 * @return  list<mixed>
	 */
	public function reindex_block_templates( array $templates ): array {
		return \array_values( $templates );
	}

	/**
	 * Switches `wptexturize()` off for a page rendered on either template.
	 *
	 * Texturize takes any `<` that does not open a tag — `i < 10` in a script — for one that runs
	 * to the next `>`, and rewrites every `&` in between to `&#038;`, which turns `a && b` into a
	 * syntax error. A block theme texturizes the whole rendered template, not only the content, so
	 * this once-per-request switch is the one lever that covers the page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   bool $run Whether texturize runs for this request.
	 *
	 * @return  bool
	 */
	public function skip_texturize( bool $run ): bool {
		if ( $run && is_page() && \in_array( get_page_template_slug( get_queried_object_id() ), self::SLUGS, true ) ) {
			return false;
		}

		return $run;
	}

	// endregion
}
