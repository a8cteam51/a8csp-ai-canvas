<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Tests\Integration;

use A8C\SpecialProjects\AICanvasWebMCP\Plugin;
use A8C\SpecialProjects\AICanvasWebMCP\Templates;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin boots on a supported runtime inside wp-env: the requirements gate passes,
 * the component registry runs, and the components register the page templates.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PluginBootTest extends TestCase {
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
		self::assertTrue( a8csp_ai_webmcp_validate_requirements() );
		self::assertTrue( \function_exists( 'a8csp_ai_webmcp_plugin' ) );
		self::assertNotFalse( has_action( 'plugins_loaded', array( a8csp_ai_webmcp_plugin(), 'boot' ) ) );
		self::assertInstanceOf( Plugin::class, a8csp_ai_webmcp_plugin() );
		self::assertTrue( a8csp_ai_webmcp_plugin()->is_booted() );
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

	// endregion.
}
