<?php declare( strict_types=1 );

/**
 * Notice-rendering WordPress stand-ins for Unit tests that invoke a staged admin-notice callback
 * and assert what it renders. Each stub is the minimal honest stand-in for its WordPress original:
 * `current_user_can()` answers from `$GLOBALS['a8csp_template_test_user_can']` so a test can drive
 * the capability guard, the i18n and escaping stubs pass their text through unchanged, and
 * `wp_admin_notice()` echoes the message so the test can capture it with output buffering. The
 * `function_exists()` guards keep this file inert wherever WordPress is loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\PluginTemplate
 */

if ( ! \function_exists( 'current_user_can' ) ) {
	/**
	 * Answers the capability check from the staged flag, defaulting to allowed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $capability The capability being checked (ignored; the staged flag decides).
	 *
	 * @return  bool
	 */
	function current_user_can( $capability ) {
		return $GLOBALS['a8csp_template_test_user_can'] ?? true;
	}
}

if ( ! \function_exists( '__' ) ) {
	/**
	 * Returns the text unchanged, standing in for translation.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $text   The text to translate.
	 * @param   string $domain The text domain (ignored).
	 *
	 * @return  string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! \function_exists( 'wp_sprintf' ) ) {
	/**
	 * Delegates to `sprintf()`, which covers every placeholder the notice strings use.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $pattern The format string.
	 * @param   mixed  ...$args The values to interpolate.
	 *
	 * @return  string
	 */
	function wp_sprintf( $pattern, ...$args ) {
		return \sprintf( $pattern, ...$args );
	}
}

if ( ! \function_exists( 'esc_html' ) ) {
	/**
	 * Returns its input unchanged, standing in for output escaping.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $text The text to escape.
	 *
	 * @return  string
	 */
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! \function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Echoes the message so a buffering test can capture what the notice renders.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string               $message The notice message.
	 * @param   array<string, mixed> $args    The notice arguments (ignored).
	 *
	 * @return  void
	 */
	function wp_admin_notice( $message, $args = array() ) {
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stand-in that echoes the message verbatim so the caller can capture it; the production caller escapes before this point.
	}
}
