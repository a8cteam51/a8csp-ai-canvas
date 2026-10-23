import { createHash } from 'crypto';
import { statSync } from 'fs';
import { readdir, readFile, writeFile } from 'fs/promises';
import { join as joinPath } from 'path';
import process from 'process';

// Approximates @wordpress/e2e-test-utils-playwright's runtime paramCase() conversion from a plugin Name header to its slug.
const toKebabCase = ( str ) =>
	str
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

const escapeRegExp = ( string ) =>
	string.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

const repository = JSON.parse( process.argv[ 2 ] );
const skippedDirectories = [ '.github', '.git' ];

// A four-port wp-env block hashed from the repository name, in 10000-29996, clear of the OS ephemeral
// ranges; distinct names can share a block, which wp-env override files resolve.
const TEMPLATE_PORT_BASE = 8890;
const nameHash = parseInt(
	createHash( 'sha256' )
		.update( repository.name )
		.digest( 'hex' )
		.slice( 0, 8 ),
	16
);
const portBase = 10000 + 4 * ( nameHash % 5000 );

/**
 * @param {string}                              dirPath
 * @param {(filePath: string) => Promise<void>} callback
 */
const traverseDirectory = async ( dirPath, callback ) => {
	if ( skippedDirectories.includes( dirPath ) ) {
		return;
	}

	const files = await readdir( dirPath );
	for ( const file of files ) {
		const filePath = joinPath( dirPath, file );

		if ( statSync( filePath ).isFile() ) {
			await callback( filePath );
		} else {
			await traverseDirectory( filePath, callback );
		}
	}
};

/**
 * Renders filePath's scaffold placeholders (README.md gets EXAMPLE_REPO_* substitutions;
 * every other file gets the A8CSP_TEMPLATE_* identifier substitutions) and overwrites it in place
 * if anything changed.
 * @param {string} filePath
 */
const buildTemplate = async ( filePath ) => {
	if ( [ 'composer.lock', 'package-lock.json' ].includes( filePath ) ) {
		// Both locks are regenerated from the substituted manifests after this pass, so
		// substituting names into them in place would only risk corrupting integrity hashes.
		return;
	}

	const templateFile = await readFile( filePath, 'utf-8' );
	let renderedTemplate = templateFile,
		replacements;

	const title = repository.custom_properties[ 'human-title' ];
	if ( 'README.md' === filePath ) {
		replacements = {
			EXAMPLE_REPO_NAME: title,
			EXAMPLE_REPO_SLUG: repository.name,
			EXAMPLE_REPO_DESCRIPTION: repository.description ?? '',
		};
	} else {
		replacements = {
			// The generators lint themselves in the template repo but self-delete at generation,
			// so they also strip their own paths from the generated repository's lint scope.
			' .github/workflows/fill-in-scaffold.mjs': '',
			' .github/workflows/fill-in-scaffold-content.mjs': '',
			'A8CSP Template Plugin': title,
			'A template for A8C Special Projects plugins.':
				repository.description ?? '',
			'a8csp/plugin-template': 'a8csp/' + repository.name,
			// The entry file's own name (fill-in-scaffold.yml already renamed it to
			// "$REPO_NAME.php" by this point) must resolve to the repo-name rule, not the
			// kebab-title rule below it; longest-first alternation tries this more specific,
			// ".php"-suffixed key before the bare kebab-title key.
			'a8csp-template-plugin.php': repository.name + '.php',
			'a8csp-template-plugin': toKebabCase( title ),
			'a8csp-plugin-template': repository.name,
			// Matches the JSON-escaped namespace form composer.json's psr-4 autoload keys carry on disk;
			// the raw namespace value is escaped by the .json rendering path.
			'A8C\\\\SpecialProjects\\\\PluginTemplate':
				'A8C\\SpecialProjects\\' +
				title.replaceAll( ' ', '' ).replace( 'A8CSP', '' ),
			'A8C\\SpecialProjects\\PluginTemplate':
				'A8C\\SpecialProjects\\' +
				title.replaceAll( ' ', '' ).replace( 'A8CSP', '' ),
			a8csp_template:
				repository.custom_properties[ 'php-globals-short-prefix' ],
			A8CSP_TEMPLATE:
				repository.custom_properties[
					'php-globals-short-prefix'
				].toUpperCase(),
		};
	}

	const replacementPattern = new RegExp(
		Object.keys( replacements )
			.sort( ( first, second ) => second.length - first.length )
			.map( escapeRegExp )
			.join( '|' ),
		'g'
	);

	renderedTemplate = renderedTemplate.replace(
		replacementPattern,
		( match ) => {
			// Substitution values land inside JSON string literals, so quotes/backslashes in free-text
			// repository metadata must be escaped to keep the document valid.
			const value = replacements[ match ];
			const renderedValue = filePath.endsWith( '.json' )
				? JSON.stringify( value ).slice( 1, -1 )
				: value;
			// A callback keeps `$` sequences in the metadata literal, and one combined pass never re-substitutes inserted text.
			return renderedValue;
		}
	);

	if ( 'README.md' !== filePath ) {
		// Port literals are bare numbers, so they replace only inside their known anchors (the
		// wp-env `"port":` keys and the tests/README ports table) — a tree-wide bare `8890` would
		// also match inside package-lock.json integrity hashes. They also bypass the replacement
		// map above: its values are JSON-escaped when landing in .json files, which would corrupt
		// a match spanning structural JSON.
		renderedTemplate = renderedTemplate.replace(
			/(?<="port": |\| )889[0-3](?=[,\s|])/g,
			( match ) =>
				String( portBase + ( Number( match ) - TEMPLATE_PORT_BASE ) )
		);
	}

	if ( filePath.endsWith( '.php' ) ) {
		// PHP files never need trailing whitespace; stripping it prevents empty descriptions from leaving phpcs-failing blank lines.
		renderedTemplate = renderedTemplate.replace( /[ \t]+$/gm, '' );
	}

	if ( renderedTemplate !== templateFile ) {
		await writeFile( filePath, renderedTemplate );
	}
};

await traverseDirectory( '.', buildTemplate );
