/**
 * The tools read and write the editor's data stores, which these tests stand in for: the page's
 * blocks and attributes, the user's permission to save scripts, and the dispatches a write makes.
 * Each test loads the tools afresh, so whether the agent has read the instructions starts false.
 */
import {
	joinContent,
	revisionOf,
} from '../../assets/js/src/webmcp/page-content';
import { INSTRUCTIONS } from '../../assets/js/src/webmcp/instructions';

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn(),
} ) );
jest.mock( '@wordpress/editor', () => ( { store: 'core/editor' } ) );
jest.mock( '@wordpress/block-editor', () => ( {
	store: 'core/block-editor',
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	createBlock: ( name, attributes, innerBlocks, innerContent ) => ( {
		name,
		attributes,
		innerBlocks,
		innerContent,
	} ),
	isUnmodifiedDefaultBlock: ( block ) =>
		'core/paragraph' === block.name && ! block.attributes.content,
} ) );
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );
jest.mock( '@wordpress/html-entities', () => ( {
	decodeEntities: ( text ) => text,
} ) );
jest.mock( '@wordpress/url', () => ( {
	addQueryArgs: ( path, args ) =>
		`${ path }?${ new URLSearchParams(
			Object.entries( args ).filter(
				( [ , value ] ) => undefined !== value
			)
		) }`,
} ) );

const TEMPLATES = {
	'header-footer': 'ai-canvas-header-footer',
	'content-only': 'ai-canvas-content-only',
};

const page = {
	css: '.canvas-proof { color: red; }',
	js: '',
	markup: '<div class="canvas-proof"><h1>Proof</h1></div>',
};

const htmlBlock = ( html ) => ( {
	clientId: 'html',
	name: 'core/html',
	attributes: { align: 'full' },
	innerBlocks: [],
	innerContent: [ html ],
} );

const emptyParagraph = {
	clientId: 'paragraph',
	name: 'core/paragraph',
	attributes: {},
	innerBlocks: [],
	innerContent: [],
};

let tools;
let editor;
let apiFetch;

/**
 * Loads the tools against an editor holding the given blocks.
 *
 * @param {Object}   editorState
 * @param {Object[]} editorState.blocks     The page's blocks.
 * @param {boolean}  editorState.unfiltered Whether the user may save scripts and styles.
 *
 * @return {Object[]} The tools, in the order the plugin registers them.
 */
function load( { blocks = [], unfiltered = true } = {} ) {
	jest.resetModules();
	window.a8csp_ai_canvas = { templates: TEMPLATES };

	const data = require( '@wordpress/data' );
	apiFetch = require( '@wordpress/api-fetch' ).default;
	editor = { resetEditorBlocks: jest.fn(), editPost: jest.fn() };

	const attributes = {
		title: 'Proof',
		template: TEMPLATES[ 'header-footer' ],
	};
	data.select.mockImplementation( ( store ) =>
		'core/block-editor' === store
			? {
					getSettings: () => ( {
						__experimentalCanUserUseUnfilteredHTML: unfiltered,
					} ),
				}
			: {
					getEditorBlocks: () => blocks,
					getEditedPostAttribute: ( key ) => attributes[ key ],
				}
	);
	data.dispatch.mockReturnValue( editor );

	const { TOOLS } = require( '../../assets/js/src/webmcp/tools' );
	tools = Object.fromEntries( TOOLS.map( ( tool ) => [ tool.name, tool ] ) );

	return TOOLS;
}

const call = ( name, input = {}, signal = new AbortController().signal ) =>
	tools[ name ].execute( input, { signal } );

describe( 'the tool set', () => {
	it( 'names each tool once, as the WebMCP spec allows', () => {
		const names = load().map( ( tool ) => tool.name );

		expect( new Set( names ).size ).toBe( names.length );
		names.forEach( ( name ) =>
			expect( name ).toMatch( /^ai-canvas-[A-Za-z0-9_.-]{1,118}$/ )
		);
	} );

	it( 'describes each tool, and its input as an object', () => {
		load().forEach( ( tool ) => {
			expect( tool.description.trim() ).not.toBe( '' );
			expect( tool.inputSchema.type ).toBe( 'object' );
		} );
	} );

	it( 'marks the page as untrusted content and the rules as not', () => {
		load();

		expect( tools[ 'ai-canvas-get-page' ].annotations ).toEqual( {
			readOnlyHint: true,
			untrustedContentHint: true,
		} );
		expect( tools[ 'ai-canvas-get-instructions' ].annotations ).toEqual( {
			readOnlyHint: true,
		} );
	} );
} );

describe( 'ai-canvas-get-instructions', () => {
	it( 'returns the authoring rules', async () => {
		load();

		await expect( call( 'ai-canvas-get-instructions' ) ).resolves.toEqual( {
			instructions: INSTRUCTIONS,
		} );
	} );
} );

describe( 'ai-canvas-get-page', () => {
	it( 'returns a page in its parts, with its revision and without the rules', async () => {
		const html = joinContent( page );
		load( { blocks: [ htmlBlock( `\n${ html }\n` ), emptyParagraph ] } );

		const result = await call( 'ai-canvas-get-page' );

		expect( result ).toMatchObject( {
			title: 'Proof',
			template: 'header-footer',
			state: 'canvas',
			canWrite: true,
			css: page.css,
			js: '',
			markup: page.markup,
			revision: revisionOf( html ),
		} );
		expect( result ).not.toHaveProperty( 'instructions' );
	} );

	it( 'reports a page built from other blocks as not writable', async () => {
		load( {
			blocks: [ { ...emptyParagraph, attributes: { content: 'Hi' } } ],
		} );

		const result = await call( 'ai-canvas-get-page' );

		expect( result.state ).toBe( 'other' );
		expect( result.canWrite ).toBe( false );
		expect( result.reason ).toMatch( /other WordPress blocks/ );
	} );

	it( 'reports a user who cannot save scripts as not writable', async () => {
		load( { unfiltered: false } );

		const result = await call( 'ai-canvas-get-page' );

		expect( result.canWrite ).toBe( false );
		expect( result.reason ).toMatch( /not allowed to save scripts/ );
	} );
} );

describe( 'ai-canvas-set-page-content', () => {
	const send = async ( input ) => {
		await call( 'ai-canvas-get-instructions' );

		return call( 'ai-canvas-set-page-content', input );
	};

	it( 'writes the page as one full-width Custom HTML block', async () => {
		load();

		const result = await send( {
			...page,
			title: ' Spring launch ',
			revision: revisionOf( '' ),
		} );

		const html = joinContent( page );
		expect( editor.resetEditorBlocks ).toHaveBeenCalledWith( [
			{
				name: 'core/html',
				attributes: { align: 'full' },
				innerBlocks: [],
				innerContent: [ html ],
			},
		] );
		expect( editor.editPost ).toHaveBeenCalledWith( {
			title: 'Spring launch',
		} );
		expect( result ).toMatchObject( {
			revision: revisionOf( html ),
			rootClass: 'canvas-proof',
		} );
	} );

	it( 'rewrites the Custom HTML block a page already is', async () => {
		const html = joinContent( page );
		load( { blocks: [ htmlBlock( html ) ] } );

		const markup = '<div class="canvas-proof"><h1>New</h1></div>';
		await send( { ...page, markup, revision: revisionOf( html ) } );

		const [ [ block ] ] = editor.resetEditorBlocks.mock.calls[ 0 ];
		expect( block.clientId ).toBe( 'html' );
		expect( block.innerContent ).toEqual( [
			joinContent( { ...page, markup } ),
		] );
	} );

	it( 'reads input passed as a JSON string, as Chrome did up to version 154', async () => {
		load();

		const result = await send(
			JSON.stringify( { ...page, revision: revisionOf( '' ) } )
		);

		expect( result.error ).toBeUndefined();
		expect( editor.resetEditorBlocks ).toHaveBeenCalled();
	} );

	it( 'refuses to write before the agent has read the instructions', async () => {
		load();

		const result = await call( 'ai-canvas-set-page-content', {
			...page,
			revision: revisionOf( '' ),
		} );

		expect( result.error ).toMatch( /ai-canvas-get-instructions/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );

	it( 'names a missing part before it checks the revision', async () => {
		load();

		const result = await send( { revision: 'stale' } );

		expect( result.error ).toMatch( /must be strings/ );
	} );

	it( 'asks for the revision when it is missing', async () => {
		load();

		const result = await send( page );

		expect( result.error ).toMatch( /Pass the `revision`/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );

	it( 'refuses a stale revision', async () => {
		load( { blocks: [ htmlBlock( joinContent( page ) ) ] } );

		const result = await send( { ...page, revision: revisionOf( '' ) } );

		expect( result.error ).toMatch( /changed since you read it/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );

	it( 'refuses a title that is not a string', async () => {
		load();

		const result = await send( {
			...page,
			title: 42,
			revision: revisionOf( '' ),
		} );

		expect( result.error ).toMatch( /`title` must be a string/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );

	it( 'refuses a page built from other blocks', async () => {
		load( {
			blocks: [ { ...emptyParagraph, attributes: { content: 'Hi' } } ],
		} );

		const result = await send( { ...page, revision: revisionOf( '' ) } );

		expect( result.error ).toMatch( /other WordPress blocks/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );

	it( 'refuses a user who cannot save scripts', async () => {
		load( { unfiltered: false } );

		const result = await send( { ...page, revision: revisionOf( '' ) } );

		expect( result.error ).toMatch( /not allowed to save scripts/ );
		expect( editor.resetEditorBlocks ).not.toHaveBeenCalled();
	} );
} );

describe( 'ai-canvas-set-template', () => {
	it( 'sets the template by its short name', async () => {
		load();

		await expect(
			call( 'ai-canvas-set-template', { template: 'content-only' } )
		).resolves.toEqual( { template: 'content-only' } );
		expect( editor.editPost ).toHaveBeenCalledWith( {
			template: TEMPLATES[ 'content-only' ],
		} );
	} );

	it( 'refuses a template that is not one of the plugin’s', async () => {
		load();

		const result = await call( 'ai-canvas-set-template', {
			template: 'nope',
		} );

		expect( result.error ).toMatch( /header-footer, content-only/ );
		expect( editor.editPost ).not.toHaveBeenCalled();
	} );
} );

describe( 'ai-canvas-list-media', () => {
	it( 'returns images with their sizes, passing the call’s abort signal on', async () => {
		load();
		apiFetch.mockResolvedValue( [
			{
				id: 7,
				source_url: 'https://example.test/flowers.webp',
				mime_type: 'image/webp',
				title: { rendered: 'Flowers' },
				alt_text: 'Flowers in a vase',
				media_details: {
					width: 1048,
					height: 1342,
					sizes: {
						medium: {
							source_url:
								'https://example.test/flowers-234x300.webp',
							width: 234,
							height: 300,
						},
					},
				},
			},
		] );
		const { signal } = new AbortController();

		const result = await call(
			'ai-canvas-list-media',
			{ search: 'flowers', limit: 500 },
			signal
		);

		const [ [ request ] ] = apiFetch.mock.calls;
		expect( request.signal ).toBe( signal );
		expect( request.path ).toMatch( /per_page=100/ );
		expect( request.path ).toMatch( /search=flowers/ );
		expect( result.media ).toEqual( [
			{
				id: 7,
				url: 'https://example.test/flowers.webp',
				mime: 'image/webp',
				title: 'Flowers',
				alt: 'Flowers in a vase',
				width: 1048,
				height: 1342,
				sizes: {
					medium: {
						url: 'https://example.test/flowers-234x300.webp',
						width: 234,
						height: 300,
					},
				},
			},
		] );
	} );

	it( 'answers a failed request with the reason, rather than throwing', async () => {
		load();
		apiFetch.mockRejectedValue( {
			code: 'rest_forbidden',
			message: 'Sorry, you are not allowed to do that.',
		} );

		const result = await call( 'ai-canvas-list-media' );

		expect( result.error ).toMatch(
			/could not be searched\. WordPress said: Sorry, you are not allowed to do that\./
		);
	} );

	it( 'answers a failed request that gives no reason', async () => {
		load();
		apiFetch.mockRejectedValue( undefined );

		const result = await call( 'ai-canvas-list-media' );

		expect( result.error ).toMatch(
			/^The Media Library could not be searched\. Try again/
		);
	} );

	it( 'lets a cancelled call end as cancelled', async () => {
		load();
		const controller = new AbortController();
		controller.abort();
		const abort = new DOMException( 'Aborted', 'AbortError' );
		apiFetch.mockRejectedValue( abort );

		await expect(
			call( 'ai-canvas-list-media', {}, controller.signal )
		).rejects.toBe( abort );
	} );
} );
