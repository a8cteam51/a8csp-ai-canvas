<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the requirements gate degrades gracefully on a below-floor runtime.
 *
 * Runs in both tiers: at or above the floor it must pass, below-floor it must yield
 * a WP_Error without loading the plugin proper.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class RequirementsCheckTest extends TestCase {
	// region TESTS.

	/**
	 * The requirements result reflects the runtime it booted on.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_requirements_gate_matches_runtime(): void {
		if ( \version_compare( $GLOBALS['wp_version'], '7.1', '<' ) ) {
			self::assertInstanceOf( \WP_Error::class, a8csp_template_validate_requirements() );
			self::assertFalse( \function_exists( 'a8csp_template_plugin' ) );
		} else {
			self::assertTrue( a8csp_template_validate_requirements() );
		}
	}

	// endregion.
}
