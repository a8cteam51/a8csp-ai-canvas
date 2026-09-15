<?php
/**
 * The content contract: what a canvas page body must look like, how a post
 * is recognised as a canvas, and the render facts check-page reports.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Content {

	const TEMPLATE_FRAMED = 'ai-canvas-framed';
	const TEMPLATE_BLANK  = 'ai-canvas-blank';
	const TEMPLATES       = array( self::TEMPLATE_FRAMED, self::TEMPLATE_BLANK );
	const MARKER_META     = '_ai_canvas';
	const MAX_BYTES       = 2097152;

	/**
	 * Map the short template names used by the tools to stored slugs.
	 * 'theme' means "no page template" (delete the meta).
	 */
	public static function template_slug( string $short ): ?string {
		return array(
			'framed' => self::TEMPLATE_FRAMED,
			'blank'  => self::TEMPLATE_BLANK,
		)[ $short ] ?? null;
	}

	public static function template_of( int $post_id ): string {
		$slug = (string) get_page_template_slug( $post_id );
		return array_search( $slug, array( 'framed' => self::TEMPLATE_FRAMED, 'blank' => self::TEMPLATE_BLANK ), true ) ?: 'theme';
	}

	public static function is_canvas( int $post_id ): bool {
		return '1' === (string) get_post_meta( $post_id, self::MARKER_META, true )
			|| in_array( (string) get_page_template_slug( $post_id ), self::TEMPLATES, true );
	}

	public static function mark( int $post_id ): void {
		update_post_meta( $post_id, self::MARKER_META, '1' );
	}

	/**
	 * Whether saving this content needs unfiltered_html (kses would otherwise
	 * silently escape the script/style tags).
	 */
	public static function needs_unfiltered_html( string $content ): bool {
		return (bool) preg_match( '/<(script|style)\b/i', $content );
	}

	/**
	 * Validate a page body against the one-block contract.
	 *
	 * @return array{error: ?WP_Error, warnings: string[]}
	 */
	public static function validate( string $content ): array {
		$warnings = array();

		if ( '' === trim( $content ) ) {
			return array( 'error' => new WP_Error( 'ai_canvas_empty_content', 'Content is empty. Send the whole Custom HTML block.' ), 'warnings' => $warnings );
		}
		if ( strlen( $content ) > self::MAX_BYTES ) {
			return array( 'error' => new WP_Error( 'ai_canvas_too_large', sprintf( 'Content exceeds the %d byte limit. Move images to the Media Library and trim the block.', self::MAX_BYTES ) ), 'warnings' => $warnings );
		}

		$blocks = array_values(
			array_filter(
				parse_blocks( $content ),
				fn( $b ) => null !== $b['blockName'] || '' !== trim( (string) $b['innerHTML'] )
			)
		);
		if ( 1 !== count( $blocks ) || 'core/html' !== $blocks[0]['blockName'] ) {
			return array(
				'error'    => new WP_Error(
					'ai_canvas_not_single_html_block',
					sprintf(
						'Content must be exactly one Custom HTML block: <!-- wp:html {"align":"full"} --> … <!-- /wp:html -->. Found %d block(s)%s.',
						count( $blocks ),
						$blocks ? ' (' . implode( ', ', array_map( fn( $b ) => $b['blockName'] ?? 'freeform', $blocks ) ) . ')' : ''
					)
				),
				'warnings' => $warnings,
			);
		}

		if ( self::needs_unfiltered_html( $content ) && ! current_user_can( 'unfiltered_html' ) ) {
			return array(
				'error'    => new WP_Error(
					'ai_canvas_unfiltered_html_required',
					'This connection user is not allowed to publish scripts and styles, so the block would be saved with its interactivity stripped out. On a multisite network only network admins can; otherwise the site defines DISALLOW_UNFILTERED_HTML. The site owner or host has to change that.'
				),
				'warnings' => $warnings,
			);
		}

		$block = $blocks[0];
		$inner = (string) $block['innerHTML'];

		if ( 'full' !== ( $block['attrs']['align'] ?? null ) ) {
			$warnings[] = 'align_not_full: the block delimiter lacks {"align":"full"}, so the page sits inside the theme content column.';
		}
		if ( preg_match( '/<!doctype|<html\b|<head\b|<body\b/i', $inner ) ) {
			$warnings[] = 'document_markup: the block contains doctype/html/head/body; it must be a fragment.';
		}
		if ( preg_match( '/<link\b|<script\b[^>]*\bsrc=/i', $inner ) ) {
			$warnings[] = 'external_resource: the block references an external stylesheet or script; keep CSS and JS inline in the block.';
		}
		if ( preg_match( '/src=["\']data:image/i', $inner ) ) {
			$warnings[] = 'data_uri_image: images belong in the Media Library, not inline as data URIs.';
		}

		$style  = self::element_inner( $inner, 'style' );
		$script = self::element_inner( $inner, 'script' );
		if ( null === $style ) {
			$warnings[] = 'missing_style_tag: no <style data-wp-block-html="css"> element found.';
		}
		if ( null === $script ) {
			$warnings[] = 'missing_script_tag: no <script data-wp-block-html="js"> element found.';
		} elseif ( self::has_bare_lt( $script ) ) {
			$warnings[] = 'bare_lt_in_script: a bare "<" inside the script can make WordPress rewrite "&" to "&#038;" on some render paths; flip the comparison or write it another way.';
		}
		if ( null !== $style && self::has_bare_lt( $style ) ) {
			$warnings[] = 'bare_lt_in_style: a bare "<" inside the style element; remove it.';
		}
		if ( null !== $script && null !== $style && ! str_contains( $style, '.is-js' ) && preg_match( '/opacity\s*:\s*0\b|display\s*:\s*none\b/i', $style ) ) {
			$warnings[] = 'hidden_state_not_gated: the CSS hides something (opacity 0 / display none) but never mentions .is-js; the editor and no-JS visitors will see blank space.';
		}

		$rest = preg_replace( '/<(style|script)\b[^>]*data-wp-block-html=[^>]*>.*?<\/\1>/is', '', $inner );
		if ( ! preg_match( '/^\s*<[a-z][a-z0-9-]*\b[^>]*\bclass=["\'][^"\']*\bcanvas-/i', (string) $rest ) ) {
			$warnings[] = 'no_root_element: the markup should start with one root element whose class begins with "canvas-".';
		}

		return array( 'error' => null, 'warnings' => $warnings );
	}

	/**
	 * Inner text of the <style|script data-wp-block-html="…"> element, or null.
	 */
	private static function element_inner( string $html, string $tag ): ?string {
		if ( preg_match( '/<' . $tag . '\b[^>]*data-wp-block-html=[^>]*>(.*?)<\/' . $tag . '>/is', $html, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/**
	 * A "<" that wptexturize would take for a tag opener. Anything that is not
	 * a real tag start (letter, slash, ! or ?) is what breaks scripts.
	 */
	private static function has_bare_lt( string $text ): bool {
		return (bool) preg_match( '/<(?![a-z\/!?])/i', $text );
	}

	/**
	 * The facts check-page reports about rendered HTML. Mirrors the checks the
	 * REST-era wp.sh script performed.
	 */
	public static function render_facts( string $html ): array {
		$script_ampersand = false;
		if ( preg_match( '/<script\b[^>]*data-wp-block-html="js"[^>]*>(.*?)<\/script>/is', $html, $m ) ) {
			$script_ampersand = str_contains( $m[1], '&#038;' );
		}
		return array(
			'alignfull_wrapper'          => str_contains( $html, 'class="wp-block-html alignfull"' ),
			'style_tag_present'          => str_contains( $html, 'data-wp-block-html="css"' ),
			'script_tag_present'         => str_contains( $html, 'data-wp-block-html="js"' ),
			'script_was_escaped'         => str_contains( $html, '&lt;script' ),
			'script_ampersand_rewritten' => $script_ampersand,
		);
	}

	/**
	 * Page summary shared by every page tool.
	 */
	public static function describe( int $post_id ): array {
		$post = get_post( $post_id );
		return array(
			'id'           => $post_id,
			'type'         => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'title'        => (string) $post->post_title,
			'slug'         => (string) $post->post_name,
			'link'         => (string) get_permalink( $post_id ),
			'edit_link'    => (string) get_edit_post_link( $post_id, 'raw' ),
			'template'     => self::template_of( $post_id ),
			'modified_gmt' => (string) $post->post_modified_gmt,
			'is_canvas'    => self::is_canvas( $post_id ),
		);
	}

	public static function summary_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'           => array( 'type' => 'integer' ),
				'type'         => array( 'type' => 'string' ),
				'status'       => array( 'type' => 'string' ),
				'title'        => array( 'type' => 'string' ),
				'slug'         => array( 'type' => 'string' ),
				'link'         => array( 'type' => 'string' ),
				'edit_link'    => array( 'type' => 'string' ),
				'template'     => array( 'type' => 'string', 'enum' => array( 'framed', 'blank', 'theme' ) ),
				'modified_gmt' => array( 'type' => 'string' ),
				'is_canvas'    => array( 'type' => 'boolean' ),
			),
		);
	}
}
