<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Integration;

use A8C\SpecialProjects\AICanvas\Connector;
use A8C\SpecialProjects\AICanvas\Mcp;
use A8C\SpecialProjects\AICanvas\Plugin;
use A8C\SpecialProjects\AICanvas\Templates;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin boots on a supported runtime inside wp-env: the requirements gate passes,
 * the component registry runs, and the components register the page templates, the page and
 * media tools, the MCP server with its instructions, and the connector.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PluginBootTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Leaves no user signed in for the next test, since this suite shares one WordPress process.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// endregion.

	// region TESTS.

	/**
	 * On an at-floor runtime the requirements gate passes, `plugins_loaded` is wired to the
	 * memoized root's `boot()` (the accessor constructs eagerly at include time, so the wiring
	 * check below resolves against the same instance), and the pipeline has run.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_plugin_boots_on_supported_runtime(): void {
		self::assertTrue( a8csp_ai_canvas_validate_requirements() );
		self::assertTrue( \function_exists( 'a8csp_ai_canvas_plugin' ) );
		self::assertNotFalse( has_action( 'plugins_loaded', array( a8csp_ai_canvas_plugin(), 'boot' ) ) );
		self::assertInstanceOf( Plugin::class, a8csp_ai_canvas_plugin() );
		self::assertTrue( a8csp_ai_canvas_plugin()->is_booted() );
	}

	/**
	 * Both templates are registered for pages, so the page editor offers them. A query for one of
	 * them comes back as a list: core's editor reads index zero of that result, and warns when a
	 * plugin-registered template is left keyed by its name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_both_templates_are_offered_for_pages(): void {
		$page_templates = wp_get_theme()->get_page_templates( null, 'page' );

		foreach ( Templates\Component::SLUGS as $slug ) {
			self::assertArrayHasKey( $slug, $page_templates );

			$templates = get_block_templates( array( 'slug__in' => array( $slug ) ) );
			self::assertArrayHasKey( 0, $templates );
			self::assertSame( $slug, $templates[0]->slug );
			self::assertStringContainsString( '<!-- wp:post-content', $templates[0]->content );
		}
	}

	/**
	 * Every page and media tool is registered as an ability, and none is exposed through the
	 * abilities REST API: the plugin's own MCP server is the only way in.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_tools_are_registered_as_abilities(): void {
		foreach ( array( 'get-instructions', 'list-pages', 'get-page', 'create-page', 'update-page', 'delete-page', 'list-media', 'upload-media' ) as $tool ) {
			$ability = wp_get_ability( 'a8csp-ai-canvas/' . $tool );

			self::assertInstanceOf( \WP_Ability::class, $ability, "the '{$tool}' tool must be registered" );
			self::assertFalse( $ability->get_meta_item( 'show_in_rest' ) );
		}
	}

	/**
	 * Each tool that writes a design to the site says, in its own description, that it waits for
	 * the user's approval: a client may read tool descriptions and not the server's instructions.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_writing_tools_carry_the_approval_rule(): void {
		foreach ( array( 'create-page', 'update-page', 'upload-media' ) as $tool ) {
			$ability = wp_get_ability( 'a8csp-ai-canvas/' . $tool );

			self::assertInstanceOf( \WP_Ability::class, $ability );
			self::assertStringContainsString( Mcp\Instructions::approval(), $ability->get_description(), "the '{$tool}' tool must carry the approval rule" );
		}
	}

	/**
	 * The upload tool says, in its own description, where a file with no public URL goes, for the
	 * same client that reads tool descriptions and not the server's instructions.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_upload_tool_carries_the_rule_for_a_file_with_no_public_url(): void {
		$ability = wp_get_ability( 'a8csp-ai-canvas/upload-media' );

		self::assertInstanceOf( \WP_Ability::class, $ability );
		self::assertStringContainsString( Mcp\Instructions::local_files(), $ability->get_description() );
	}

	/**
	 * The MCP server answers `initialize` on its own route with the authoring guide as its
	 * instructions, carrying the link to this site's upload screen, for a user who can edit pages.
	 *
	 * @since   1.0.0
	 * @version 1.0.1
	 *
	 * @return  void
	 */
	public function test_the_mcp_server_serves_the_instructions(): void {
		wp_set_current_user( self::an_administrator_id() );

		$request = new \WP_REST_Request( 'POST', '/' . Mcp\Component::ROUTE_NAMESPACE . '/' . Mcp\Component::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => 'initialize',
					'params'  => array(
						'protocolVersion' => '2025-06-18',
						'capabilities'    => new \stdClass(),
						'clientInfo'      => array(
							'name'    => 'integration-test',
							'version' => '1',
						),
					),
				)
			)
		);

		$response = rest_do_request( $request );
		$data     = \json_decode( (string) wp_json_encode( $response->get_data() ), true );

		self::assertSame( 200, $response->get_status() );
		self::assertIsArray( $data );
		self::assertSame( Mcp\Instructions::text( admin_url( 'media-new.php' ) ), $data['result']['instructions'] ?? null );
	}

	/**
	 * Without a signed-in user the MCP route refuses the request.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_mcp_server_refuses_an_anonymous_request(): void {
		$request = new \WP_REST_Request( 'POST', '/' . Mcp\Component::ROUTE_NAMESPACE . '/' . Mcp\Component::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' );

		self::assertContains( rest_do_request( $request )->get_status(), array( 401, 403 ) );
	}

	/**
	 * The connector is registered with core, without credentials of its own: the card's script
	 * supplies its whole UI, and core stores nothing for it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_connector_is_registered_with_core(): void {
		$connector = wp_get_connector( Connector\Component::CONNECTOR_ID );

		self::assertIsArray( $connector );
		self::assertSame( 'AI Canvas', $connector['name'] );
		self::assertSame( array( 'method' => 'none' ), $connector['authentication'] );
		self::assertSame( \constant( 'A8CSP_AI_CANVAS_DIR_URL' ) . 'assets/images/connector-logo.png', $connector['logo_url'] ?? null );
		self::assertFileExists( \constant( 'A8CSP_AI_CANVAS_DIR_PATH' ) . 'assets/images/connector-logo.png' );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Returns the ID of an administrator. wp-env's fixture always provisions one; querying for it
	 * keeps the test independent of its ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	private static function an_administrator_id(): int {
		$users = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		self::assertNotEmpty( $users, 'wp-env must provision an administrator' );

		return (int) $users[0];
	}

	// endregion.
}
