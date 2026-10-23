<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\PluginTemplate;

\defined( 'ABSPATH' ) || exit;

/**
 * Defaults-only base for components: an always-open gate and a no-op readiness phase — the
 * Laravel ServiceProvider / Symfony AbstractBundle shape. It exists ONLY to absorb no-op
 * boilerplate and must never grow state, shared behavior, or helpers; that road leads back to
 * inheritance-tree frameworks. Everything in the plugin types against `ComponentInterface`, so
 * extending is optional — implement the interface directly whenever any default doesn't fit or
 * the explicit methods teach better.
 *
 * Every method fulfilling the contract — here and in every component — carries `#[\Override]`.
 * Without it, renaming a contract method that has a concrete default leaves the implementation
 * silently orphaned rather than failing at compile time.
 *
 * @internal
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class AbstractComponent implements ComponentInterface {
	// region METHODS

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
	public function initialize(): void {}

	// endregion
}
