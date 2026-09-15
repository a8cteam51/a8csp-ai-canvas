<?php
/**
 * Front-end rendering: the two page templates, the full-width wrapper for the
 * Custom HTML block, and the wptexturize guard for canvas pages.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Render {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_templates' ) );
		// Registry-provided templates come back keyed by name ("ai-canvas//…"),
		// but core reads $templates[0] (e.g. wp_get_post_content_block_attributes()),
		// spraying warnings on the edit screen. Normalize to the documented list shape.
		add_filter( 'get_block_templates', fn( $templates ) => array_values( (array) $templates ) );
		add_filter( 'render_block_core/html', array( __CLASS__, 'wrap_html_block' ), 10, 2 );
		add_filter( 'register_block_type_args', array( __CLASS__, 'add_html_align_support' ), 10, 2 );
		add_filter( 'the_content', array( __CLASS__, 'suspend_texturize' ), 1 );
		add_filter( 'run_wptexturize', array( __CLASS__, 'run_texturize' ) );
	}

	/**
	 * Jamie's Visual HTML Editor wraps aligned Custom HTML blocks itself and adds
	 * the editor-side alignment control; defer to it when active.
	 */
	public static function jvhe_active(): bool {
		return class_exists( 'JVHE_Plugin' );
	}

	public static function register_templates(): void {
		// core/template-part blocks (not PHP block_template_part()) so the theme's
		// wrapper element and attributes are preserved; core injects the theme attribute.
		register_block_template(
			'ai-canvas//' . AI_Canvas_Content::TEMPLATE_FRAMED,
			array(
				'title'       => __( 'AI Canvas (with header and footer)', 'ai-canvas' ),
				'description' => __( 'Site header, the page content, site footer. No page title.', 'ai-canvas' ),
				'post_types'  => array( 'page', 'post' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
					. '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">'
					. '<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->'
					. '</main><!-- /wp:group -->'
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
			)
		);

		register_block_template(
			'ai-canvas//' . AI_Canvas_Content::TEMPLATE_BLANK,
			array(
				'title'       => __( 'AI Canvas (blank)', 'ai-canvas' ),
				'description' => __( 'Only the page content. No header, footer, or page title.', 'ai-canvas' ),
				'post_types'  => array( 'page', 'post' ),
				'content'     => '<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->',
			)
		);
	}

	/**
	 * core/html has no save wrapper, so its `align` attribute never reaches the
	 * front end. Wrap the rendered HTML so the theme's constrained-layout CSS
	 * breaks it out. $content is core's raw block output and is deliberately not
	 * escaped; the only value added is a hard-coded class from an allow-list.
	 */
	public static function wrap_html_block( string $content, array $block ): string {
		if ( self::jvhe_active() ) {
			return $content;
		}
		$align = $block['attrs']['align'] ?? '';
		if ( 'full' === $align ) {
			return '<div class="wp-block-html alignfull">' . $content . '</div>';
		}
		if ( 'wide' === $align ) {
			return '<div class="wp-block-html alignwide">' . $content . '</div>';
		}
		return $content;
	}

	/**
	 * Declare align support on core/html so the editor keeps {"align":"full"}
	 * when the owner saves the page in wp-admin, and shows the alignment control.
	 */
	public static function add_html_align_support( array $args, string $name ): array {
		if ( 'core/html' === $name && ! self::jvhe_active() ) {
			$args['supports']          = $args['supports'] ?? array();
			$args['supports']['align'] = array( 'wide', 'full' );
		}
		return $args;
	}

	/**
	 * wptexturize treats any bare "<" as a tag opener and rewrites "&" to
	 * "&#038;" up to the next ">", which breaks scripts inside the block.
	 * Block themes texturize the whole rendered template
	 * (get_the_block_template_html()), not just the_content, so the only lever
	 * that covers a canvas page is the once-per-request run_wptexturize
	 * short-circuit. It is decided on the first wptexturize() call, which on
	 * the front end happens after the main query is parsed.
	 */
	public static function run_texturize( $run ) {
		if ( $run && is_singular() && AI_Canvas_Content::is_canvas( (int) get_queried_object_id() ) ) {
			return false;
		}
		return $run;
	}

	/**
	 * Belt and braces for contexts where texturize was already switched on
	 * before the canvas post rendered (REST, CLI, feeds): suspend it around
	 * the_content the same way core's do_blocks() suspends wpautop.
	 */
	public static function suspend_texturize( $content ) {
		$post_id = get_the_ID();
		if ( $post_id && AI_Canvas_Content::is_canvas( (int) $post_id ) ) {
			$priority = has_filter( 'the_content', 'wptexturize' );
			if ( false !== $priority && $priority < 11 ) {
				remove_filter( 'the_content', 'wptexturize', $priority );
				add_filter( 'the_content', array( __CLASS__, 'restore_texturize' ), 11 );
			}
		}
		return $content;
	}

	public static function restore_texturize( $content ) {
		remove_filter( 'the_content', array( __CLASS__, 'restore_texturize' ), 11 );
		add_filter( 'the_content', 'wptexturize' );
		return $content;
	}
}
