import { access, readFile, unlink, writeFile } from 'fs/promises';
import process from 'process';

// A newline-joined block of source lines. Manifest spans are authored line by line so leading tabs
// and blank comment lines (`\t *`) are unambiguous in this file's own source.
const block = ( ...lines ) => lines.join( '\n' );

// The teaching-content strip manifest. It runs after fill-in-scaffold.mjs, so no span may contain a
// substitutable token: each must read the same before and after that pass.
//   { action: 'replace-exact', path, from, to } — `from` must occur exactly once in `path`.
//   { action: 'delete', path }                  — `path` must exist.
const MANIFEST = [
	// includes/_disabled-example.php exists only to teach the underscore opt-out convention.
	{ action: 'delete', path: 'includes/_disabled-example.php' },

	{
		action: 'replace-exact',
		path: 'functions.php',
		from: block(
			' *',
			' * Construction only — never boots: a peer calling this at include time would otherwise run the',
			' * component gates before every plugin has loaded. Booting stays tied to the `plugins_loaded`',
			' * attachment in the main plugin file.',
			' *'
		),
		to: block(
			' *',
			' * Construction only; it never boots the plugin.',
			' *'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/Plugin.php',
		from: block(
			'/**',
			" * The plugin's composition root: `COMPONENTS` below is the plugin, and `boot()` runs it through",
			' * the `ComponentCollection`. This is the one file you edit to wire a top-level component in —',
			' * should the plugin ever outgrow manual wiring, the class list here evolves into root-owned',
			' * factories and a PSR-11 container takes over `ComponentCollection::assemble()`; the component',
			' * classes themselves stay untouched.',
			' *',
			' * The layout convention: one folder per feature, each owning a `Component` that composes it; the',
			' * `src/` root holds only this bootstrapping mechanism. The root itself deliberately does not',
			' * implement `ComponentInterface`: it runs the contract, so it cannot also be subject to it.',
			' *'
		),
		to: block(
			'/**',
			" * The plugin's composition root: assembles the top-level components and runs the boot pipeline.",
			' *'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Plugin.php',
		from: block(
			'\t * Runs the boot pipeline: gate the plugin on its host, assemble the component collection,',
			'\t * initialize every surviving component, then let every component register its hooks.',
			'\t *',
			'\t * The components take no constructor arguments; the day one needs shared state injected,',
			'\t * build the boundary here before `assemble()` — evolving the class list into factories that',
			"\t * receive it is the collection's next shape, not today's. A boot failure propagates",
			'\t * uncaught — fail loud; the entry latch already guarantees it cannot be retried into',
			'\t * duplicate hook registrations.'
		),
		to: block(
			"\t * Runs the plugin's boot pipeline.",
			'\t *',
			'\t * A boot failure propagates uncaught — fail loud; the entry latch already guarantees it cannot',
			'\t * be retried into duplicate hook registrations.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Plugin.php',
		from: block(
			'\t\t$components->initialize();',
			'',
			'\t\t// The root seam: every contribution is in, no hook is live yet — cross-component',
			'\t\t// registries are validated and frozen here when the plugin grows some.',
			'',
			'\t\t$components->register_hooks();'
		),
		to: block(
			'\t\t$components->initialize();',
			'\t\t$components->register_hooks();'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Plugin.php',
		from: block(
			'\t * Whether the host requirements hold for this request: WooCommerce present, at the',
			'\t * header-declared `WC requires at least` floor. On failure, stages the explanatory notice on',
			'\t * `all_admin_notices` and returns false.',
			'\t *',
			'\t * This is the machinery of the plugin-wide rung of the `should_load()` ladder; the rung itself',
			'\t * is the one call in `boot()`, gating the whole plugin before any component exists. A failed',
			'\t * gate leaves the plugin un-booted and non-retryable for the request, with `is_booted()`',
			'\t * reporting false. The misconfiguration speaks through a notice instead of silently gating',
			'\t * off — the requirements-gate philosophy. `plugins_loaded` is the earliest the check is',
			'\t * reliable; at include time the host may simply not have loaded yet. A plugin that is not',
			'\t * WooCommerce-dependent deletes this region and the one `boot()` line.',
			'\t *'
		),
		to: block(
			'\t * Whether the host requirements hold for this request: WooCommerce present and at the',
			'\t * header-declared `WC requires at least` floor. On failure, stages an admin notice and returns',
			'\t * false.',
			'\t *',
			'\t * `plugins_loaded` is the earliest the check is reliable; at include time the host may simply',
			'\t * not have loaded yet.',
			'\t *'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/AbstractComponent.php',
		from: block(
			'/**',
			' * Defaults-only base for components: an always-open gate and a no-op readiness phase — the',
			' * Laravel ServiceProvider / Symfony AbstractBundle shape. It exists ONLY to absorb no-op',
			' * boilerplate and must never grow state, shared behavior, or helpers; that road leads back to',
			' * inheritance-tree frameworks. Everything in the plugin types against `ComponentInterface`, so',
			" * extending is optional — implement the interface directly whenever any default doesn't fit or",
			' * the explicit methods teach better.'
		),
		to: block(
			'/**',
			' * Defaults-only base for components, supplying the no-op defaults of `ComponentInterface`. It',
			' * must never grow state, shared behavior, or helpers; every component types against the',
			' * interface, so extending this base is optional.'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/ComponentCollection.php',
		from: block(
			'/**',
			' * A gated collection of components: the component-loop machinery, owned once. The composition',
			' * root and any group root delegate their gate-construct and phase loops here instead of',
			' * hand-rolling them — has-a, not is-a: the collection runs components, so it does not implement',
			' * `ComponentInterface` itself.'
		),
		to: block(
			'/**',
			' * A collection of components that gates its members on construction and runs their lifecycle',
			' * phases. Owns the component-loop machinery once for the composition root and every group root.'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/ComponentInterface.php',
		from: block(
			' *',
			' * Each feature lives in its own folder under `src/` and exposes exactly one implementation of',
			" * this contract — its `Component` — as the feature's composition point; descriptive leaf classes",
			' * inside a feature keep their own names. The `Interface` suffix follows the PSR interface',
			' * convention (`ContainerInterface`, `LoggerInterface`) and frees the `Component` name for the',
			' * implementations that folder convention creates.',
			' *'
		),
		to: block( ' *' ),
	},
	{
		action: 'replace-exact',
		path: 'src/ComponentInterface.php',
		from: block(
			'\t *',
			'\t * The gate recurs at four rungs of one ladder: the whole plugin (the host gate in',
			'\t * `Plugin::boot()`), a feature subtree (a parent component whose closed gate leaves everything',
			"\t * its `initialize()` would have constructed unbuilt), a leaf component's own gate, and finally",
			'\t * those capability checks inside hook callbacks.',
			'\t *'
		),
		to: block( '\t *' ),
	},

	{
		action: 'replace-exact',
		path: 'src/Blocks/Component.php',
		from: block(
			'/**',
			' * Composes the Blocks feature: registers every built block from the build manifest as one',
			' * metadata collection and registers a block-editor script. Blocks have no environmental',
			' * dependency and no state to wire, so the defaults-only base fits.',
			' *',
			' * Imitate this manifest registration shape for any block work. Delete this feature folder, its',
			' * `COMPONENTS` entry, and the `blocks/` directory if your plugin ships no blocks.',
			' *'
		),
		to: block(
			'/**',
			" * Composes the Blocks feature: registers the plugin's blocks and the block-editor assets.",
			' *'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Blocks/Component.php',
		from: block(
			"\t * Registers all blocks from the build's metadata manifest collection: one filesystem",
			'\t * read for the whole collection instead of one per block directory.'
		),
		to: block(
			"\t * Registers the plugin's blocks from the build's metadata manifest."
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Blocks/Component.php',
		from: block(
			'\t * Registers and enqueues the plugin-level block-editor script. A registered-but-not-enqueued',
			'\t * handle never loads, so the editor hook hub in `assets/js/src/editor.js` — the entry point that',
			'\t * fires `editor.ready` for any editor extensions the plugin ships — only runs once the handle is',
			'\t * enqueued here.',
			'\t *',
			'\t * Blocks receive their script translations automatically from the `textdomain` in `block.json`;',
			'\t * a script registered by hand, like this one, loads them only through',
			'\t * `wp_set_script_translations()`, which reads the JSON files `composer internationalize`',
			'\t * generates with `wp i18n make-json`. The hub has no strings of its own, so this call is what',
			'\t * translates any string an editor extension adds to the bundle.'
		),
		to: block(
			"\t * Registers and enqueues the plugin's block-editor script and its translations."
		),
	},

	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			'/**',
			" * Composes the Settings feature: it owns both of the plugin's settings surfaces — one option",
			' * registered and persisted through the WordPress Settings API on the General options page, and',
			' * one section with a persisted option in WooCommerce → Settings → Advanced. Core functionality',
			' * behind the plugin-wide host gate: WooCommerce is guaranteed, no surface needs a gate of its',
			' * own, and the defaults-only base fits. Both surfaces double as the worked example for the',
			' * uninstall footprint.',
			' *',
			' * Imitate this feature for any option your plugin owns. Delete the surface — or the whole',
			' * feature folder — your plugin does not need.',
			' *'
		),
		to: block(
			'/**',
			" * Composes the Settings feature: owns the plugin's two example settings surfaces — an option on",
			' * the General options page and a section in WooCommerce → Settings → Advanced.',
			' *'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			'\t * Hangs the demo field on the core General options page to avoid inventing a whole admin page',
			'\t * for a one-field demo. Imitate the `register_setting()` shape and retarget the page as needed.'
		),
		to: block(
			'\t * Registers the example option on the core General options page.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			'\t * Renders the persisted value as a text field. Escaping on output is the point this example',
			'\t * models, even though the value is also sanitized before WordPress persists it.',
			'\t * The value comes through the typed reader in `includes/settings.php`, the worked example of',
			'\t * reading an option this component registers.'
		),
		to: block( "\t * Renders the example option's settings field." ),
	},
	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			"\t * Enqueues the admin stylesheet on the General options page — the surface this component's demo",
			'\t * field lives on. Gating on the hook suffix keeps the stylesheet off every other admin screen,',
			'\t * the worked example of a scoped admin enqueue. The compiled asset carries its version and',
			'\t * dependencies through the same asset-meta helper the block script uses, and',
			"\t * `wp_style_add_data( …, 'rtl', 'replace' )` swaps in the built `-rtl.css` on right-to-left",
			'\t * locales.'
		),
		to: block(
			'\t * Enqueues the settings stylesheet on the General options page.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			"\t * Adds the plugin's section to the Advanced settings tab. The section slug carries the prefix",
			'\t * token so generation rewrites it, and the label doubles as the plugin title.'
		),
		to: block(
			"\t * Adds the plugin's section to the Advanced settings tab."
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Settings/Component.php',
		from: block(
			"\t * Declares this section's WooCommerce settings rows. WooCommerce persists the field through its",
			'\t * own settings save, so declaring the field is the whole persistence story and the option key',
			'\t * still appears in the uninstallation footprint.'
		),
		to: block( "\t * Declares this section's WooCommerce settings rows." ),
	},

	{
		action: 'replace-exact',
		path: 'src/Integrations/Component.php',
		from: block(
			' *',
			" * The children are full Components, and this parent forwards the pipeline's phases to them",
			' * faithfully through the shared `ComponentCollection`: construction and readiness inside',
			' * `initialize()`, hooks inside `register_hooks()`. Children come in two shapes — a single-class',
			' * leaf with a descriptive name, and a grown sub-feature folder owning its own `Component`; a leaf',
			' * is promoted to the folder shape the day it needs a second class. This template nests exactly',
			' * one level; a plugin that finds itself wanting more is being told it has outgrown manual',
			" * composition — the authors' cue to consider a dependency injection container behind the",
			' * composition root, a judgment call that is theirs to make.',
			' *',
			" * The group's own gate stays at the base's always-open default because the children gate",
			' * individually; a gate every child shares — one companion they all require — would override',
			' * `should_load()` here instead, sparing each child the repetition.',
			' *'
		),
		to: block( ' *' ),
	},
	{
		action: 'replace-exact',
		path: 'src/Integrations/Component.php',
		from: block(
			'\t * Assembles and initializes the children — the same order the composition root uses, one',
			'\t * level down.'
		),
		to: block(
			"\t * Assembles and initializes the group's child components."
		),
	},

	{
		action: 'replace-exact',
		path: 'src/Integrations/WooCommerceSubscriptions/Component.php',
		from: block(
			'/**',
			' * Composes the WooCommerce Subscriptions integration: it extends what the plugin already does',
			' * instead of smuggling in a feature of its own, and it gates on its companion so none of it is',
			' * constructed when Subscriptions is absent.',
			' *',
			' * This is what a leaf integration becomes the day it needs a second class: a folder owning a',
			' * `Component` plus plain collaborators. The collaborators are plain final classes, not',
			' * `ComponentInterface` implementers, so the forwarding depth is still one — contrast with a',
			' * Component whose children are Components, which is the dependency-injection-container signal.',
			' *'
		),
		to: block(
			'/**',
			' * Composes the WooCommerce Subscriptions integration, gated on the Subscriptions plugin being',
			' * active.',
			' *'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Integrations/WooCommerceSubscriptions/Component.php',
		from: block(
			'\t * Returns true when WooCommerce Subscriptions is active. The companion gate is the whole point',
			'\t * of an integration component: everything below may assume Subscriptions exists.'
		),
		to: block(
			'\t * Whether WooCommerce Subscriptions is active; once constructed, the integration may assume it is.'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/Integrations/WooCommerceSubscriptions/PriceNote.php',
		from: block(
			'/**',
			' * Builds the demonstration note appended to subscription price strings; replace this with the',
			" * integration's real behavior.",
			' *',
			" * A plain collaborator: the feature's `Component` constructs it in `initialize()` and attaches",
			' * its callback in `register_hooks()`; it implements no plugin contract of its own.'
		),
		to: block(
			'/**',
			' * Builds the note appended to subscription price strings.'
		),
	},

	{
		action: 'replace-exact',
		path: 'src/Integrations/WooPayments.php',
		from: block(
			'/**',
			' * Provides the WooPayments integration — the worked example of the simple leaf shape: one',
			' * descriptively-named class, one companion gate, one honest filter. A leaf is promoted to a',
			' * folder owning its own `Component` the day it needs a second class; the WooCommerce',
			' * Subscriptions sibling shows that grown shape.'
		),
		to: block(
			'/**',
			' * The WooPayments integration: adds example metadata to the payment data WooPayments builds',
			' * from an order, when the WooPayments plugin is active.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Integrations/WooPayments.php',
		from: block(
			'\t * Returns true when WooPayments is active. The companion gate is the whole point of an',
			'\t * integration component: everything below may assume WooPayments exists.'
		),
		to: block(
			'\t * Whether WooPayments is active; once constructed, the integration may assume it is.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Integrations/WooPayments.php',
		from: block(
			"\t * Adds the template's demonstration entry to the payment metadata WooPayments generates from",
			"\t * an order; replace this with the integration's real behavior."
		),
		to: block(
			'\t * Adds a demonstration entry to the payment metadata WooPayments generates from an order.'
		),
	},
	// The @api/@internal split is carried by the tags themselves; the prose teaching it is
	// template-only.
	{
		action: 'replace-exact',
		path: 'functions.php',
		from: block(
			'// This accessor and the prefixed `get_plugin_slug()`, `get_plugin_name()` and `get_plugin_version()`',
			"// getters in functions-bootstrap.php are the plugin's supported surface for a peer plugin; the",
			'// kernel classes behind them carry `@internal` and may change shape without notice.',
			'',
			''
		),
		to: '',
	},

	// `#[\\Override]` stays on every implementation; the passage explaining why is template-only.
	{
		action: 'replace-exact',
		path: 'src/AbstractComponent.php',
		from: block(
			' * Every method fulfilling the contract — here and in every component — carries `#[\\Override]`.',
			' * Without it, renaming a contract method that has a concrete default leaves the implementation',
			' * silently orphaned rather than failing at compile time.',
			' *',
			''
		),
		to: '',
	},

	{
		action: 'replace-exact',
		path: 'includes/settings.php',
		from: block(
			" * Returns the example option's value. Each typed option reader names its option, applies its",
			' * default, and casts the return so callers never touch raw `get_option()` mixed values. The write',
			" * side — registration, sanitization, and rendering — lives in the Settings feature's component",
			' * in `src/Settings/Component.php`.'
		),
		to: block( " * Returns the example option's value." ),
	},
	{
		action: 'replace-exact',
		path: 'includes/assets.php',
		from: block(
			' * Returns an array with meta information for a given asset path. It starts from a fallback of the',
			" * file's last-modified time as the version with no dependencies, overlays the version and",
			' * dependencies from an `.asset.php` file beside the asset when one exists, and appends any extra',
			' * dependencies passed in. A malformed generated payload is ignored entry by entry, so a stale or',
			' * hand-edited `.asset.php` degrades to the fallback rather than enqueuing a broken handle.'
		),
		to: block(
			' * Returns the version and dependencies for a given asset path, or null when the asset does not',
			' * exist.'
		),
	},
	{
		action: 'replace-exact',
		path: 'src/Integrations/WooCommerceSubscriptions/PriceNote.php',
		from: block(
			"\t * Appends the template's demonstration note to the companion-generated subscription price",
			'\t * string.'
		),
		to: block(
			'\t * Appends the example note to the subscription price string.'
		),
	},
	{
		action: 'replace-exact',
		path: 'assets/css/src/settings.scss',
		from: block(
			' *',
			' * `Settings\\Component::enqueue_admin_styles()` enqueues the compiled `settings.css` only on that',
			' * screen. The physical `border-left` here is deliberate: it is what the RTL build step flips to',
			' * `border-right` in the generated `settings-rtl.css`. Reach for logical properties in real styles;',
			' * the physical property is the worked example that makes the RTL variant meaningfully different.',
			''
		),
		to: '',
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/editor.scss',
		from: block(
			' *',
			' * Replace them with your own styles or remove the file completely.',
			''
		),
		to: '',
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/style.scss',
		from: block(
			' *',
			' * Replace them with your own styles or remove the file completely.',
			''
		),
		to: '',
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/block.json',
		from: block( ' Copy this block to build your own.' ),
		to: '',
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/edit.js',
		from: block(
			'/**',
			' * Retrieves the translation of text.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/',
			' */',
			"import { __ } from '@wordpress/i18n';",
			'',
			'/**',
			' * React hook that is used to mark the block wrapper element.',
			' * It provides all the necessary props like the class name.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops',
			' */',
			"import { useBlockProps } from '@wordpress/block-editor';",
			'',
			'/**',
			' * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.',
			' * Those files can contain any CSS code that gets applied to the editor.',
			' *',
			' * @see https://www.npmjs.com/package/@wordpress/scripts#using-css',
			' */',
			"import './editor.scss';",
			'',
			'/**',
			' * The edit function describes the structure of your block in the context of the',
			' * editor. This represents what the editor will render when the block is used.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit',
			' *',
			' * @return {Element} Element to render.',
			' */'
		),
		to: block(
			'/**',
			' * WordPress dependencies',
			' */',
			"import { __ } from '@wordpress/i18n';",
			"import { useBlockProps } from '@wordpress/block-editor';",
			'',
			'/**',
			' * Internal dependencies',
			' */',
			"import './editor.scss';",
			'',
			'/**',
			' * Renders the block in the editor.',
			' *',
			' * @return {Element} Element to render.',
			' */'
		),
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/index.js',
		from: block(
			'/**',
			' * Registers a new block provided a unique name and an object defining its behavior.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/',
			' */',
			"import { registerBlockType } from '@wordpress/blocks';",
			'',
			'/**',
			' * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.',
			' * All files containing `style` keyword are bundled together. The code used',
			' * gets applied both to the front of your site and to the editor.',
			' *',
			' * @see https://www.npmjs.com/package/@wordpress/scripts#using-css',
			' */',
			"import './style.scss';",
			'',
			'/**',
			' * Internal dependencies',
			' */',
			"import Edit from './edit';",
			"import save from './save';",
			"import metadata from './block.json';",
			'',
			'/**',
			' * Every block starts by registering a new block type definition.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/',
			' */',
			'registerBlockType( metadata.name, {',
			'\t/**',
			'\t * @see ./edit.js',
			'\t */',
			'\tedit: Edit,',
			'',
			'\t/**',
			'\t * @see ./save.js',
			'\t */',
			'\tsave,',
			'} );'
		),
		to: block(
			'/**',
			' * WordPress dependencies',
			' */',
			"import { registerBlockType } from '@wordpress/blocks';",
			'',
			'/**',
			' * Internal dependencies',
			' */',
			"import './style.scss';",
			"import Edit from './edit';",
			"import save from './save';",
			"import metadata from './block.json';",
			'',
			'registerBlockType( metadata.name, {',
			'\tedit: Edit,',
			'\tsave,',
			'} );'
		),
	},
	{
		action: 'replace-exact',
		path: 'blocks/src/example-notice/save.js',
		from: block(
			'/**',
			' * React hook that is used to mark the block wrapper element.',
			' * It provides all the necessary props like the class name.',
			' *',
			' * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops',
			' */',
			"import { useBlockProps } from '@wordpress/block-editor';",
			'',
			'/**',
			' * The save function defines the way in which the different attributes should',
			' * be combined into the final markup, which is then serialized by the block',
			' * editor into `post_content`.',
			' *'
		),
		to: block(
			'/**',
			' * WordPress dependencies',
			' */',
			"import { useBlockProps } from '@wordpress/block-editor';",
			'',
			'/**',
			" * Renders the block's saved markup.",
			' *'
		),
	},
];

const checkOnly = process.argv.includes( '--check' );

const fileExists = async ( path ) => {
	try {
		await access( path );
		return true;
	} catch {
		return false;
	}
};

// Every entry is checked before anything is written, so a drifted span fails the run with the tree
// untouched; `--check` runs the same pass and stops there. Entries against one file apply in
// manifest order to one buffer.
const errors = [];
const buffers = new Map();
const deletes = [];
for ( const { action, path, from, to } of MANIFEST ) {
	if ( 'delete' === action ) {
		if ( await fileExists( path ) ) {
			deletes.push( path );
		} else {
			errors.push( `delete: ${ path } does not exist` );
		}
		continue;
	}

	const span = from.split( '\n' )[ 0 ];
	if ( ! buffers.has( path ) ) {
		if ( ! ( await fileExists( path ) ) ) {
			errors.push(
				`replace-exact: ${ path } does not exist for span starting "${ span }"`
			);
			continue;
		}
		buffers.set( path, await readFile( path, 'utf-8' ) );
	}

	const buffer = buffers.get( path );
	const occurrences = buffer.split( from ).length - 1;
	if ( 1 !== occurrences ) {
		errors.push(
			`replace-exact: ${ path } — span occurs ${ occurrences } times (want exactly 1): "${ span }"`
		);
		continue;
	}
	// A function replacer inserts the text verbatim; a string replacement would interpret $-patterns inside it.
	buffers.set(
		path,
		buffer.replace( from, () => to )
	);
}

if ( 0 !== errors.length ) {
	console.error(
		'fill-in-scaffold-content: manifest failed with %d violation(s):',
		errors.length
	);
	for ( const error of errors ) {
		console.error( '  - %s', error );
	}
	process.exit( 1 );
}

if ( ! checkOnly ) {
	for ( const [ path, buffer ] of buffers ) {
		await writeFile( path, buffer );
	}
	for ( const path of deletes ) {
		await unlink( path );
	}
}
