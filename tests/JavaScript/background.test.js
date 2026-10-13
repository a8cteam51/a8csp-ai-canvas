import {
	findUrls,
	formatUrl,
	locateBackground,
} from '../../assets/js/src/visual-editor/background';
import { DomMap } from '../../assets/js/src/visual-editor/dom-map';

describe( 'findUrls', () => {
	it( 'finds the URLs of a stylesheet, with the rule each is in', () => {
		const css =
			'.hero { background: linear-gradient(#000, #fff), url("a.png") center / cover; }\n' +
			"@media (min-width: 600px) { .hero,\n .banner { background-image: url( 'b.png' ); } }\n" +
			'.plain { background: URL(c.png); }';
		const urls = findUrls( css );

		expect(
			urls.map( ( { url, quote, selector } ) => [ url, quote, selector ] )
		).toEqual( [
			[ 'a.png', '"', '.hero' ],
			[ 'b.png', "'", '.hero, .banner' ],
			[ 'c.png', '', '.plain' ],
		] );
		expect( css.slice( urls[ 0 ].start, urls[ 0 ].end ) ).toBe(
			'url("a.png")'
		);
		expect( css.slice( urls[ 1 ].start, urls[ 1 ].end ) ).toBe(
			"url( 'b.png' )"
		);
	} );

	it( 'finds the URL of a style attribute', () => {
		const [ url ] = findUrls(
			'color: red; background-image: url(/img/a b.png)'
		);

		expect( url ).toMatchObject( {
			url: '/img/a b.png',
			quote: '',
			selector: null,
		} );
	} );

	it( 'skips comments, strings and names that end in url', () => {
		expect(
			findUrls(
				'/* url(no.png) */ .a { content: "url(no.png)"; --my-url(no.png): 1; background: url(yes.png); }'
			).map( ( { url } ) => url )
		).toEqual( [ 'yes.png' ] );
	} );

	it( 'undoes the escapes of a URL', () => {
		expect( findUrls( 'a { b: url("a\\"b.png"); }' )[ 0 ].url ).toBe(
			'a"b.png'
		);
		expect( findUrls( 'a { b: url(a\\29 b.png); }' )[ 0 ].url ).toBe(
			'a)b.png'
		);
	} );
} );

describe( 'formatUrl', () => {
	it( 'writes the URL in the quotes the old one had', () => {
		expect( formatUrl( 'https://example.test/a.png', '"' ) ).toBe(
			'url("https://example.test/a.png")'
		);
		expect( formatUrl( 'https://example.test/a.png', "'" ) ).toBe(
			"url('https://example.test/a.png')"
		);
		expect( formatUrl( 'https://example.test/a.png', '' ) ).toBe(
			'url(https://example.test/a.png)'
		);
	} );

	it( 'quotes a URL that cannot stand bare, and escapes its quotes', () => {
		expect( formatUrl( '/a b.png', '' ) ).toBe( 'url("/a b.png")' );
		expect( formatUrl( '/a"b.png', '"' ) ).toBe( 'url("/a\\"b.png")' );
	} );
} );

describe( 'locateBackground', () => {
	const load = ( html ) => {
		document.body.innerHTML = '<div id="root"></div>';
		const root = document.getElementById( 'root' );
		const map = new DomMap( document );
		root.replaceChildren( map.load( html ) );

		return { map, root };
	};

	it( 'finds the image in the element’s own style attribute', () => {
		const { map, root } = load(
			'<style>.b { color: red; }</style><div class="b" style="background-image: linear-gradient(#000, #fff), url(\'/img/hero.png\')">x</div>'
		);
		const found = locateBackground( root.querySelector( '.b' ), root, map );

		expect( found.style ).toBeNull();
		expect( found.text.slice( found.token.start, found.token.end ) ).toBe(
			"url('/img/hero.png')"
		);
	} );

	it( 'finds the image in the rule that names the element', () => {
		const { map, root } = load(
			'<style>.a { background: url("/img/shared.png"); }\n.hero { background: url("/img/shared.png") center; }</style><div class="hero">x</div>'
		);
		const hero = root.querySelector( '.hero' );
		const found = locateBackground( hero, root, map );

		expect( found.style ).toBe( root.querySelector( 'style' ) );
		expect( found.token.selector ).toBe( '.hero' );

		// And the change lands on that rule alone.
		map.replaceRawText(
			found.style,
			found.token.start,
			found.token.end,
			formatUrl( '/img/new.png', found.token.quote )
		);
		expect( map.html ).toBe(
			'<style>.a { background: url("/img/shared.png"); }\n.hero { background: url("/img/new.png") center; }</style><div class="hero">x</div>'
		);
	} );

	it( 'finds nothing for an element with no background image', () => {
		const { map, root } = load(
			'<style>.a { background: red; }</style><div class="a">x</div>'
		);

		expect(
			locateBackground( root.querySelector( '.a' ), root, map )
		).toBeNull();
	} );
} );
