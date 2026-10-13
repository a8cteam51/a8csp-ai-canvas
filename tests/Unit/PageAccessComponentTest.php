<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Tests\Unit;

use A8C\SpecialProjects\AICanvas\AbstractComponent;
use A8C\SpecialProjects\AICanvas\PageAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the page access component outside WordPress: its hook phase stages the route, both
 * switches and the column, and the column is added only for a user with the capability, ahead of
 * the date.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
#[CoversClass( PageAccess\Component::class )]
#[UsesClass( AbstractComponent::class )]
final class PageAccessComponentTest extends TestCase {
	// region LIFECYCLE.

	/**
	 * Loads the stand-ins the column callback reads: the capability check and translation.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-notice-stubs.php';
	}

	/**
	 * Starts each test with an empty hook-registration ledger and a capable user.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['a8csp_ai_canvas_test_hooks']    = array();
		$GLOBALS['a8csp_ai_canvas_test_user_can'] = true;
	}

	// endregion.

	// region TESTS.

	/**
	 * The hook phase stages the switches' route, both switches' scripts, and the column with its
	 * cells.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_register_hooks_stages_the_route_and_both_switches(): void {
		$component = new PageAccess\Component();
		$component->initialize();
		$component->register_hooks();

		self::assertSame(
			array( 'rest_api_init', 'enqueue_block_editor_assets', 'admin_enqueue_scripts', 'manage_pages_columns', 'manage_pages_custom_column' ),
			$GLOBALS['a8csp_ai_canvas_test_hooks']
		);
	}

	/**
	 * For a user with the capability the column is added ahead of the date, and last when the
	 * list has no date column.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_column_is_added_ahead_of_the_date(): void {
		$component = new PageAccess\Component();

		self::assertSame(
			array( 'title', 'author', 'a8csp-ai-canvas', 'date' ),
			\array_keys(
				$component->add_column(
					array(
						'title'  => 'Title',
						'author' => 'Author',
						'date'   => 'Date',
					)
				)
			)
		);
		self::assertSame(
			array( 'title', 'a8csp-ai-canvas' ),
			\array_keys( $component->add_column( array( 'title' => 'Title' ) ) )
		);
	}

	/**
	 * For anyone else the list's columns come back untouched.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function test_the_column_is_not_added_for_other_users(): void {
		$GLOBALS['a8csp_ai_canvas_test_user_can'] = false;

		$columns = array(
			'title' => 'Title',
			'date'  => 'Date',
		);

		self::assertSame( $columns, ( new PageAccess\Component() )->add_column( $columns ) );
	}

	// endregion.
}
