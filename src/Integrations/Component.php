<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvasWebMCP\Integrations;

use A8C\SpecialProjects\AICanvasWebMCP\AbstractComponent;
use A8C\SpecialProjects\AICanvasWebMCP\ComponentInterface;
use A8C\SpecialProjects\AICanvasWebMCP\ComponentCollection;

\defined( 'ABSPATH' ) || exit;

/**
 * The Integrations group root: one component that composes the plugin's optional integrations —
 * peers that extend the plugin when a companion plugin happens to be active.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Component extends AbstractComponent {
	// region FIELDS AND CONSTANTS

	/**
	 * Add the group's child components here; they run through each phase in registration order.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array<int, class-string<ComponentInterface>>
	 */
	private const array COMPONENTS = array(
		WooPayments::class,
		WooCommerceSubscriptions\Component::class,
	);

	/**
	 * The assembled child collection; set during `initialize()`, null until then.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     ComponentCollection|null
	 */
	private ?ComponentCollection $components = null;

	// endregion

	// region METHODS

	/**
	 * Assembles and initializes the group's child components.
	 *
	 * The survivors are constructed and initialized here, never lazily in `register_hooks()` —
	 * deferring construction to the hook phase would hand peers a half-built component, the exact
	 * bug the two-phase pipeline exists to prevent.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	#[\Override]
	public function initialize(): void {
		$this->components = ComponentCollection::assemble( self::COMPONENTS );
		$this->components->initialize();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \LogicException If the hook phase runs before initialization.
	 */
	#[\Override]
	public function register_hooks(): void {
		$components = $this->components ?? throw new \LogicException( 'Integrations group hooked before initialization.' );

		$components->register_hooks();
	}

	// endregion
}
