import { tokenize } from '../../assets/js/src/visual-editor/tokenizer';

const names = ( html ) =>
	tokenize( html ).map(
		( tag ) => ( tag.type === 'end' ? '/' : '' ) + tag.name
	);

describe( 'tokenize', () => {
	it( 'finds start and end tags with their offsets', () => {
		const html = '<div class="a"><p>Hi</p></div>';
		const tags = tokenize( html );

		expect( names( html ) ).toEqual( [ 'div', 'p', '/p', '/div' ] );
		expect( html.slice( tags[ 0 ].start, tags[ 0 ].end ) ).toBe(
			'<div class="a">'
		);
		expect( html.slice( tags[ 2 ].start, tags[ 2 ].end ) ).toBe( '</p>' );
		expect( tags[ 0 ].nameEnd ).toBe( 4 );
	} );

	it( 'lowercases tag and attribute names', () => {
		const [ tag ] = tokenize( '<DIV CLASS="A" Data-X=1>' );

		expect( tag.name ).toBe( 'div' );
		expect( tag.attrs.map( ( { name } ) => name ) ).toEqual( [
			'class',
			'data-x',
		] );
	} );

	it( 'reads quoted, unquoted and valueless attributes', () => {
		const html = `<a href="/x?a=1&amp;b=2" title='it''s' target=_blank download data-a = "1">`;
		const [ tag ] = tokenize( html );
		const values = tag.attrs.map( ( attribute ) => [
			attribute.name,
			attribute.valueStart === -1
				? null
				: html.slice( attribute.valueStart, attribute.valueEnd ),
			attribute.quote,
		] );

		expect( values ).toEqual( [
			[ 'href', '/x?a=1&amp;b=2', '"' ],
			[ 'title', 'it', "'" ],
			[ "'s'", null, '' ],
			[ 'target', '_blank', '' ],
			[ 'download', null, '' ],
			[ 'data-a', '1', '"' ],
		] );
	} );

	it( 'does not end a tag at a > inside a quoted value', () => {
		const html = '<a title="a > b" href="#">x</a>';
		const tags = tokenize( html );

		expect( names( html ) ).toEqual( [ 'a', '/a' ] );
		expect( html.slice( tags[ 0 ].start, tags[ 0 ].end ) ).toBe(
			'<a title="a > b" href="#">'
		);
	} );

	it( 'keeps a slash that belongs to an unquoted value', () => {
		const [ tag ] = tokenize( '<a href=/menu/>' );

		expect( tag.selfClosing ).toBe( false );
		expect( tag.attrs[ 0 ].valueEnd - tag.attrs[ 0 ].valueStart ).toBe( 6 );
	} );

	it( 'flags self-closing tags', () => {
		const tags = tokenize( '<br/><img src="a" /><div/>' );

		expect( tags.map( ( tag ) => tag.selfClosing ) ).toEqual( [
			true,
			true,
			true,
		] );
	} );

	it( 'skips comments, doctypes and processing instructions', () => {
		expect(
			names(
				'<!DOCTYPE html><!-- <b>not a tag</b> --><p><!--> <i>x</i><?php <u> ?></p>'
			)
		).toEqual( [ 'p', 'i', '/i', '/p' ] );
	} );

	it( 'treats a < that opens nothing as text', () => {
		expect( names( '<p>1 < 2 and a <3 b</p>' ) ).toEqual( [ 'p', '/p' ] );
	} );

	it( 'reads the content of a script and a style as text', () => {
		const html =
			'<style>.a > b { content: "</p>"; }</style>' +
			'<script>if (a < b && c > d) { x = "<div>"; }</script><p></p>';

		expect( names( html ) ).toEqual( [
			'style',
			'/style',
			'script',
			'/script',
			'p',
			'/p',
		] );
	} );

	it( 'ends a script at its first end tag, whatever its case', () => {
		expect( names( '<script>var a = 1;</SCRIPT ><b></b>' ) ).toEqual( [
			'script',
			'/script',
			'b',
			'/b',
		] );
	} );

	it( 'does not end a script inside a commented-out script', () => {
		const html =
			'<script><!-- <script>alert(1)</script> --></script><b></b>';

		expect( names( html ) ).toEqual( [ 'script', '/script', 'b', '/b' ] );
		expect( html.slice( tokenize( html )[ 1 ].start ) ).toBe(
			'</script><b></b>'
		);
	} );

	it( 'reads the content of a textarea and a title as text', () => {
		expect(
			names(
				'<textarea><b>x</b></textarea><title><i>y</i></title><p></p>'
			)
		).toEqual( [ 'textarea', '/textarea', 'title', '/title', 'p', '/p' ] );
	} );

	it( 'stops at a raw text element that never ends', () => {
		expect( names( '<p></p><script>var a = "<b>";' ) ).toEqual( [
			'p',
			'/p',
			'script',
		] );
	} );

	it( 'stops at a tag the HTML ends inside', () => {
		expect( names( '<p>x</p><a href="' ) ).toEqual( [ 'p', '/p' ] );
	} );

	it( 'marks the tags of inline SVG as foreign', () => {
		const tags = tokenize(
			'<p><svg viewBox="0 0 1 1"><path d="M0 0"/><g><circle r="1"/></g></svg></p><b></b>'
		);

		expect(
			tags
				.filter( ( tag ) => tag.type === 'start' )
				.map( ( tag ) => [ tag.name, tag.foreign ] )
		).toEqual( [
			[ 'p', false ],
			[ 'svg', true ],
			[ 'path', true ],
			[ 'g', true ],
			[ 'circle', true ],
			[ 'b', false ],
		] );
	} );

	it( 'reads a style and a title inside SVG as markup', () => {
		expect(
			names(
				'<svg><title>A <b>bold</b> title</title><style><![CDATA[ a > b { } <i> ]]></style></svg>'
			)
		).toEqual( [
			'svg',
			'title',
			'b',
			'/b',
			'/title',
			'style',
			'/style',
			'/svg',
		] );
	} );

	it( 'goes back to HTML rules inside a foreignObject', () => {
		const tags = tokenize(
			'<svg><foreignObject><div><style>a > b {}</style><br></div></foreignObject><rect/></svg>'
		);

		expect(
			tags
				.filter( ( tag ) => tag.type === 'start' )
				.map( ( tag ) => [ tag.name, tag.foreign ] )
		).toEqual( [
			[ 'svg', true ],
			[ 'foreignobject', true ],
			[ 'div', false ],
			[ 'style', false ],
			[ 'br', false ],
			[ 'rect', true ],
		] );
	} );

	it( 'leaves nested SVG roots to their own end tags', () => {
		const tags = tokenize(
			'<svg><svg><g/></svg><g/></svg><style>a>b</style>'
		);

		expect(
			tags
				.filter( ( tag ) => tag.type === 'start' )
				.map( ( tag ) => [ tag.name, tag.foreign ] )
		).toEqual( [
			[ 'svg', true ],
			[ 'svg', true ],
			[ 'g', true ],
			[ 'g', true ],
			[ 'style', false ],
		] );
	} );

	it( 'leaves SVG at an HTML tag a browser would break out on', () => {
		const tags = tokenize( '<svg><g><p>x</p><style>a > b {}</style>' );

		expect(
			tags.map( ( tag ) => [ tag.type, tag.name, tag.foreign ] )
		).toEqual( [
			[ 'start', 'svg', true ],
			[ 'start', 'g', true ],
			[ 'start', 'p', false ],
			[ 'end', 'p', false ],
			[ 'start', 'style', false ],
			[ 'end', 'style', false ],
		] );
	} );
} );
