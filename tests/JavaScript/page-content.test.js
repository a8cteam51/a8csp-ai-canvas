import {
	checkContent,
	joinContent,
	revisionOf,
	splitContent,
} from '../../assets/js/src/webmcp/page-content';

const page = {
	css: '.canvas-proof { color: red; }',
	js: '(function () {})();',
	markup: '<div class="canvas-proof"><h1>Proof</h1></div>',
};

describe( 'joinContent', () => {
	it( 'puts the style, the script and the markup in that order', () => {
		expect( joinContent( page ) ).toBe(
			'<style data-wp-block-html="css">\n.canvas-proof { color: red; }\n</style>\n\n' +
				'<script data-wp-block-html="js">\n(function () {})();\n</script>\n\n' +
				'<div class="canvas-proof"><h1>Proof</h1></div>'
		);
	} );

	it( 'leaves out an empty style or script element', () => {
		expect( joinContent( { ...page, css: ' ', js: '' } ) ).toBe(
			page.markup
		);
	} );
} );

describe( 'splitContent', () => {
	it( 'takes back apart what joinContent joined', () => {
		expect( splitContent( joinContent( page ) ) ).toEqual( page );
	} );

	it( 'reads a page without a script', () => {
		expect( splitContent( joinContent( { ...page, js: '' } ) ) ).toEqual( {
			...page,
			js: '',
		} );
	} );

	it( 'keeps HTML that does not open with the block’s elements as markup', () => {
		const html = '<p>Hello</p><style data-wp-block-html="css">p {}</style>';

		expect( splitContent( html ) ).toEqual( {
			css: '',
			js: '',
			markup: html,
		} );
	} );

	it( 'reads an empty block as an empty page', () => {
		expect( splitContent( '' ) ).toEqual( { css: '', js: '', markup: '' } );
	} );
} );

describe( 'checkContent', () => {
	it( 'accepts one root element with a class, and names the class', () => {
		expect( checkContent( page ) ).toEqual( { rootClass: 'canvas-proof' } );
	} );

	it( 'ignores whitespace and comments around the root element', () => {
		expect(
			checkContent( {
				...page,
				markup: '\n<!-- hero -->\n<section class="canvas-proof x"></section>\n',
			} )
		).toEqual( { rootClass: 'canvas-proof' } );
	} );

	it.each( [
		[ 'two root elements', '<div class="a"></div><div class="b"></div>' ],
		[ 'text beside the root', 'Hi <div class="a"></div>' ],
		[ 'no element', 'Just text' ],
		[ 'a root without a class', '<div><p>x</p></div>' ],
		[ 'a document', '<!DOCTYPE html><html><body class="a"></body></html>' ],
		[
			'a block comment',
			'<!-- wp:paragraph --><p class="a">x</p><!-- /wp:paragraph -->',
		],
	] )( 'refuses markup with %s', ( _, markup ) => {
		expect( checkContent( { ...page, markup } ).error ).toEqual(
			expect.any( String )
		);
	} );

	it( 'refuses a stylesheet that would close its element', () => {
		expect(
			checkContent( { ...page, css: 'a {}</style><p>' } ).error
		).toEqual( expect.any( String ) );
	} );

	it( 'refuses a script that would close its element', () => {
		expect(
			checkContent( { ...page, js: 'x();</SCRIPT>' } ).error
		).toEqual( expect.any( String ) );
	} );

	it( 'refuses parts that are not strings', () => {
		expect( checkContent( { ...page, js: undefined } ).error ).toEqual(
			expect.any( String )
		);
	} );
} );

describe( 'revisionOf', () => {
	it( 'is eight hexadecimal digits', () => {
		expect( revisionOf( '' ) ).toMatch( /^[0-9a-f]{8}$/ );
		expect( revisionOf( joinContent( page ) ) ).toMatch( /^[0-9a-f]{8}$/ );
	} );

	it( 'is the same for the same HTML and differs for a change', () => {
		const html = joinContent( page );

		expect( revisionOf( html ) ).toBe( revisionOf( `${ html }` ) );
		expect( revisionOf( html ) ).not.toBe(
			revisionOf( html.replace( 'Proof', 'Proofs' ) )
		);
	} );

	it( 'is the same with or without the line breaks the database keeps around the HTML', () => {
		const html = joinContent( page );

		expect( revisionOf( `\n${ html }\n` ) ).toBe( revisionOf( html ) );
	} );
} );
