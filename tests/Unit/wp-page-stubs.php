<?php declare( strict_types=1 );
/**
 * Page-query WordPress stand-ins for Unit tests that drive the Templates component without
 * WordPress. `is_page()` answers from `$GLOBALS['a8csp_ai_canvas_test_is_page']` and
 * `get_page_template_slug()` from `$GLOBALS['a8csp_ai_canvas_test_page_template']`, so a test can
 * stage which request it is. The `function_exists()` guards keep this file inert wherever WordPress
 * is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\AICanvas
 */

if ( ! \function_exists( 'is_page' ) ) {
	/**
	 * Answers whether the request is for a page from the staged flag.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	function is_page() {
		return $GLOBALS['a8csp_ai_canvas_test_is_page'] ?? false;
	}
}

if ( ! \function_exists( 'get_queried_object_id' ) ) {
	/**
	 * Returns a fixed page ID; the staged template decides the outcome.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	function get_queried_object_id() {
		return 1;
	}
}

if ( ! \function_exists( 'get_page_template_slug' ) ) {
	/**
	 * Returns the staged page template slug.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $post The page ID (ignored; the staged slug decides).
	 *
	 * @return  string
	 */
	function get_page_template_slug( $post = null ) {
		return $GLOBALS['a8csp_ai_canvas_test_page_template'] ?? '';
	}
}
