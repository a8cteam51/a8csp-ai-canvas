<?php
/**
 * Client-compatibility shims for the MCP endpoint.
 *
 * 1. Stateless clients: the adapter requires an Mcp-Session-Id on every
 *    request after initialize. A client that does not echo the header back
 *    (or reconnects per turn) would get "Missing Mcp-Session-Id header" on
 *    every tool call. Mint a session for the authenticated user on the fly.
 * 2. Bare credentials: a connector admin pasting base64("user:app-password")
 *    without the "Basic " scheme sends an Authorization header core ignores.
 *    Hand it to core's Application Password handler as if it were Basic.
 *
 * Both apply only to requests for this plugin's MCP route.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Compat {

	public static function init(): void {
		add_filter( 'determine_current_user', array( __CLASS__, 'accept_bare_basic_credentials' ), 5 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'ensure_session' ), 10, 3 );
	}

	private static function is_mcp_request(): bool {
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return str_contains( $uri, '/ai-canvas/mcp' ) || str_contains( $uri, 'rest_route=%2Fai-canvas%2Fmcp' ) || str_contains( $uri, 'rest_route=/ai-canvas/mcp' );
	}

	/**
	 * Runs before core's Application Password check (priority 20). Never
	 * authenticates by itself: it only populates PHP_AUTH_USER/PHP_AUTH_PW so
	 * wp_validate_application_password() does the real work.
	 */
	public static function accept_bare_basic_credentials( $user_id ) {
		if ( $user_id || ! self::is_mcp_request() || isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			return $user_id;
		}
		$header = trim( (string) ( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $header || str_contains( $header, ' ' ) ) {
			return $user_id; // Empty, or carries a scheme (Basic/Bearer) that core or another plugin owns.
		}
		$decoded = base64_decode( $header, true );
		if ( false === $decoded || ! str_contains( $decoded, ':' ) ) {
			return $user_id;
		}
		list( $login, $password ) = explode( ':', $decoded, 2 );
		if ( '' === $login || '' === $password ) {
			return $user_id;
		}
		$_SERVER['PHP_AUTH_USER'] = $login;
		$_SERVER['PHP_AUTH_PW']   = $password;
		return $user_id;
	}

	/**
	 * Mint a session for authenticated POSTs that arrive without one and are
	 * not (only) an initialize request.
	 */
	public static function ensure_session( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $result;
		}
		if ( '/ai-canvas/mcp' !== $request->get_route() || $request->get_header( 'Mcp-Session-Id' ) ) {
			return $result;
		}
		if ( ! class_exists( '\WP\MCP\Transport\Infrastructure\SessionManager' ) ) {
			return $result;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $result;
		}
		$body     = $request->get_json_params();
		$messages = isset( $body['method'] ) ? array( $body ) : (array) $body;
		foreach ( $messages as $message ) {
			if ( is_array( $message ) && 'initialize' === ( $message['method'] ?? '' ) ) {
				return $result; // The router creates the session for initialize itself.
			}
		}
		$session_id = \WP\MCP\Transport\Infrastructure\SessionManager::create_session( $user_id, array( 'ai_canvas_stateless' => true ) );
		if ( is_string( $session_id ) ) {
			$request->set_header( 'Mcp-Session-Id', $session_id );
		}
		return $result;
	}
}
