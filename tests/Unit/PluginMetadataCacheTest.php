<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Proves the metadata reader tracks plugin load order: extra plugin headers — WooCommerce's
 * `WC requires at least` — exist only once the plugin registering them has loaded, and this
 * plugin can load first. The include-time requirements read must therefore not be memoized
 * into the host gate's later `plugins_loaded` read.
 *
 * The reader keeps per-request state in a function static, so the test runs in its own process
 * to observe a fresh one.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class PluginMetadataCacheTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Satisfies the bootstrap file's `ABSPATH` guard and constants, stages the WordPress stubs,
	 * then loads the real `functions-bootstrap.php` under test.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		\defined( 'ABSPATH' ) || \define( 'ABSPATH', '/tmp/' );
		\defined( 'A8CSP_AI_CANVAS_FILE' ) || \define( 'A8CSP_AI_CANVAS_FILE', '/tmp/plugins/a8csp-ai-canvas/a8csp-ai-canvas.php' );

		require_once __DIR__ . '/wp-bootstrap-stubs.php';
		require_once \dirname( __DIR__, 2 ) . '/functions-bootstrap.php';
	}

	// endregion.

	// region TESTS.

	/**
	 * Walks the reader through the request timeline: the include-time read (before any plugin
	 * beyond this one has loaded) misses the WooCommerce header and must not be cached; the
	 * `plugins_loaded` read sees it and latches; later mutations no longer change the answer.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[RunInSeparateProcess]
	public function test_the_host_gate_read_sees_headers_registered_after_include_time(): void {
		$GLOBALS['a8csp_ai_canvas_test_actions']     = array();
		$GLOBALS['a8csp_ai_canvas_test_plugin_data'] = array( 'Name' => 'A8CSP AI Canvas' );
		self::assertNull( a8csp_ai_canvas_get_plugin_metadata( 'WC requires at least' ), 'The include-time read runs before the plugin registering the extra header loads, so the header is absent' );

		$GLOBALS['a8csp_ai_canvas_test_actions']     = array( 'plugins_loaded' => 1 );
		$GLOBALS['a8csp_ai_canvas_test_plugin_data'] = array(
			'Name'                 => 'A8CSP AI Canvas',
			'WC requires at least' => '11.1',
		);
		self::assertSame( '11.1', a8csp_ai_canvas_get_plugin_metadata( 'WC requires at least' ), 'A read at plugins_loaded must see the header another plugin registered after this plugin was included' );

		$GLOBALS['a8csp_ai_canvas_test_plugin_data'] = array( 'Name' => 'mutated' );
		self::assertSame( '11.1', a8csp_ai_canvas_get_plugin_metadata( 'WC requires at least' ), 'A read taken once every plugin has loaded is stable and memoized for the request' );
	}

	// endregion.
}
