<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves the defaults-only base holds its two defaults for an extending component: an always-open
 * gate and a readiness phase with no observable effect.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( AbstractComponent::class )]
final class AbstractComponentTest extends TestCase {
	// region TESTS.

	/**
	 * An extending component inherits the open gate and a readiness phase that registers nothing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_defaults_hold_for_an_extending_component(): void {
		$GLOBALS['a8csp_template_test_hooks'] = array();

		$component = new class() extends AbstractComponent {
			// region METHODS.

			/**
			 * {@inheritDoc}
			 *
			 * @since   1.0.0
			 * @version 1.0.0
			 */
			#[\Override]
			public function register_hooks(): void {
				add_action( 'a8csp_template_test_hook', static function (): void {} );
			}

			// endregion.
		};

		self::assertTrue( $component::should_load() );

		$component->initialize();
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );

		$component->register_hooks();
		self::assertSame( array( 'a8csp_template_test_hook' ), $GLOBALS['a8csp_template_test_hooks'] );
	}

	// endregion.
}
