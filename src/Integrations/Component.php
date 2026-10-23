<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate\Integrations;

use A8C\SpecialProjects\PluginTemplate\AbstractComponent;
use A8C\SpecialProjects\PluginTemplate\ComponentInterface;
use A8C\SpecialProjects\PluginTemplate\ComponentCollection;

\defined( 'ABSPATH' ) || exit;

/**
 * The Integrations group root: one component that composes the plugin's optional integrations —
 * peers that extend the plugin when a companion plugin happens to be active.
 *
 * The children are full Components, and this parent forwards the pipeline's phases to them
 * faithfully through the shared `ComponentCollection`: construction and readiness inside
 * `initialize()`, hooks inside `register_hooks()`. Children come in two shapes — a single-class
 * leaf with a descriptive name, and a grown sub-feature folder owning its own `Component`; a leaf
 * is promoted to the folder shape the day it needs a second class. This template nests exactly
 * one level; a plugin that finds itself wanting more is being told it has outgrown manual
 * composition — the authors' cue to consider a dependency injection container behind the
 * composition root, a judgment call that is theirs to make.
 *
 * The group's own gate stays at the base's always-open default because the children gate
 * individually; a gate every child shares — one companion they all require — would override
 * `should_load()` here instead, sparing each child the repetition.
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
	 * Assembles and initializes the children — the same order the composition root uses, one
	 * level down.
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
