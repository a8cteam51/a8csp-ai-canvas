<?php
/**
 * MCP server exposing the AI-Canvas abilities at /wp-json/ai-canvas/mcp.
 *
 * Requires the WordPress MCP Adapter plugin (>= 0.6.1). Without it the
 * abilities still register; there is just no MCP endpoint.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_MCP {

	const SERVER_ID = 'ai-canvas';

	public static function init(): void {
		add_action( 'mcp_adapter_init', array( __CLASS__, 'create_server' ) );
		add_filter( 'mcp_adapter_initialize_response', array( __CLASS__, 'add_instructions' ), 10, 2 );
	}

	public static function create_server( $adapter ): void {
		$adapter->create_server(
			self::SERVER_ID,
			'ai-canvas',
			'mcp',
			'AI-Canvas',
			'Build and edit pages and posts on this WordPress site as a single full-width Custom HTML block (markup, CSS and JS), with revisions for undo and Media Library access. Call get-guidelines before the first write.',
			AI_CANVAS_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			null,
			null,
			AI_Canvas_Abilities::ABILITIES,
			array(),
			array(),
			// Transport-level gate: without it any logged-in user (Subscribers
			// included) can enumerate tool names/descriptions. Per-tool
			// permission callbacks still apply on top.
			fn() => AI_Canvas_Abilities::can_use_server()
		);
	}

	/**
	 * The adapter uses the server description as `instructions`; replace it
	 * with the full authoring guide so a client with no skill loaded still
	 * receives the contract on connect.
	 */
	public static function add_instructions( $result, $server ) {
		if ( ! is_object( $server ) || ! method_exists( $server, 'get_server_id' ) || self::SERVER_ID !== $server->get_server_id() ) {
			return $result;
		}
		if ( ! is_object( $result ) || ! method_exists( $result, 'toArray' ) ) {
			return $result;
		}
		$data                 = $result->toArray();
		$data['instructions'] = AI_Canvas_Guidelines::text();
		return \WP\McpSchema\Common\Protocol\DTO\InitializeResult::fromArray( $data );
	}
}
