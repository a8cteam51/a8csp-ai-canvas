<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Tests\Unit;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\Blocks;
use A8C\SpecialProjects\PluginTemplate\ComponentCollection;
use A8C\SpecialProjects\PluginTemplate\ComponentInterface;
use A8C\SpecialProjects\PluginTemplate\Integrations\WooCommerceSubscriptions;
use A8C\SpecialProjects\PluginTemplate\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the shared component collection outside WordPress: gates run before construction so a
 * closed gate keeps a component out of the map entirely, activity is answerable by class, the
 * readiness phase registers nothing, and every component's readiness phase completes before any
 * component's hook phase runs.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( ComponentCollection::class )]
#[UsesClass( AbstractComponent::class )]
#[UsesClass( Blocks\Component::class )]
#[UsesClass( Settings\Component::class )]
#[UsesClass( WooCommerceSubscriptions\Component::class )]
final class ComponentCollectionTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Starts each test with empty hook and phase-event ledgers.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_template_test_hooks']        = array();
		$GLOBALS['a8csp_template_test_phase_events'] = array();
	}

	// endregion.

	// region TESTS.

	/**
	 * A closed gate keeps its component out of the collection — no construction, no activity —
	 * while an open gate admits its component; assembly itself registers nothing. The closed-gate
	 * recording fixture logs its own construction, so an empty event log proves it was never built.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_assemble_gates_before_construction(): void {
		$components = ComponentCollection::assemble( array( Settings\Component::class, WooCommerceSubscriptions\Component::class, ClosedGateRecordingComponent::class ) );

		self::assertTrue( $components->has( Settings\Component::class ) );
		self::assertFalse( $components->has( WooCommerceSubscriptions\Component::class ) );
		self::assertSame( array(), $GLOBALS['a8csp_template_test_phase_events'] );
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );
	}

	/**
	 * The readiness loop registers nothing, and the hook loop registers every survivor's hooks. The
	 * set of hooks is the claim; the order they register in is not observable behavior.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_hook_phase_registers_every_survivors_hooks(): void {
		$components = ComponentCollection::assemble( array( Blocks\Component::class, Settings\Component::class ) );

		$components->initialize();
		self::assertSame( array(), $GLOBALS['a8csp_template_test_hooks'] );

		$components->register_hooks();
		self::assertContains( 'init', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'enqueue_block_editor_assets', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'admin_init', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'admin_enqueue_scripts', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_sections_advanced', $GLOBALS['a8csp_template_test_hooks'] );
		self::assertContains( 'woocommerce_get_settings_advanced', $GLOBALS['a8csp_template_test_hooks'] );
	}

	/**
	 * The phase contract: every component's readiness phase completes before any component's hook
	 * phase runs. Two recording fixtures log a `init:*`/`hooks:*` event per phase; the proof is that
	 * every readiness event precedes every hook event, without pinning the order within either phase.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_readiness_completes_before_any_hook_registration(): void {
		$components = ComponentCollection::assemble( array( FirstPhaseRecordingComponent::class, SecondPhaseRecordingComponent::class ) );

		$components->initialize();
		$components->register_hooks();

		$events         = $GLOBALS['a8csp_template_test_phase_events'];
		$last_readiness = self::last_index( $events, 'init:' );
		$first_hook     = self::first_index( $events, 'hooks:' );

		self::assertCount( 2, \array_filter( $events, static fn ( string $event ): bool => \str_starts_with( $event, 'init:' ) ) );
		self::assertCount( 2, \array_filter( $events, static fn ( string $event ): bool => \str_starts_with( $event, 'hooks:' ) ) );
		self::assertLessThan( $first_hook, $last_readiness, 'every readiness event must precede every hook event' );
	}

	// endregion.

	// region HELPERS.

	/**
	 * Returns the highest index in the event log whose entry begins with the given prefix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int, string> $events The recorded phase events.
	 * @param   string             $prefix The event prefix to match.
	 *
	 * @return  int
	 */
	private static function last_index( array $events, string $prefix ): int {
		$index = null;
		foreach ( $events as $position => $event ) {
			if ( \str_starts_with( $event, $prefix ) ) {
				$index = $position;
			}
		}

		self::assertIsInt( $index, "no event with the '{$prefix}' prefix was recorded" );

		return $index;
	}

	/**
	 * Returns the lowest index in the event log whose entry begins with the given prefix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<int, string> $events The recorded phase events.
	 * @param   string             $prefix The event prefix to match.
	 *
	 * @return  int
	 */
	private static function first_index( array $events, string $prefix ): int {
		foreach ( $events as $position => $event ) {
			if ( \str_starts_with( $event, $prefix ) ) {
				return $position;
			}
		}

		self::fail( "no event with the '{$prefix}' prefix was recorded" );
	}

	// endregion.
}

/**
 * A phase-recording component fixture: logs `init:first` in the readiness phase and `hooks:first`
 * in the hook phase, so a test can prove the collection completes every readiness phase before any
 * hook phase.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class FirstPhaseRecordingComponent implements ComponentInterface {
	// region METHODS.

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function initialize(): void {
		$GLOBALS['a8csp_template_test_phase_events'][] = 'init:first';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		$GLOBALS['a8csp_template_test_phase_events'][] = 'hooks:first';
	}

	// endregion.
}

/**
 * A second phase-recording component fixture, distinct from the first so the proof can show one
 * component's readiness phase precedes the other component's hook phase.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class SecondPhaseRecordingComponent implements ComponentInterface {
	// region METHODS.

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function initialize(): void {
		$GLOBALS['a8csp_template_test_phase_events'][] = 'init:second';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		$GLOBALS['a8csp_template_test_phase_events'][] = 'hooks:second';
	}

	// endregion.
}

/**
 * A closed-gate component fixture whose constructor logs `construct:closed`, so a test can prove the
 * collection never constructs a component its gate keeps out.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class ClosedGateRecordingComponent extends AbstractComponent {
	// region CONSTRUCTORS.

	/**
	 * ClosedGateRecordingComponent constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function __construct() {
		$GLOBALS['a8csp_template_test_phase_events'][] = 'construct:closed';
	}

	// endregion.

	// region METHODS.

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {}

	// endregion.
}
