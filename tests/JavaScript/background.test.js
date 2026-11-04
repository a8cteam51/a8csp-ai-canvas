import {
	applyEdit,
	findUrls,
	focalPointOf,
	formatPosition,
	formatUrl,
	locateBackground,
	removalOf,
	selectorNames,
	setDeclaration,
	splitList,
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

describe( 'findUrls and the rule a URL is in', () => {
	it( 'gives the body of the rule, from after its brace to its closing one', () => {
		const css =
			'@media (min-width: 600px) { .hero { background: url(a.png); color: red; } }';
		const [ token ] = findUrls( css );

		expect( css.slice( token.block.start, token.block.end ) ).toBe(
			' background: url(a.png); color: red; '
		);
		expect( token.selector ).toBe( '.hero' );
	} );

	it( 'gives no rule for a style attribute', () => {
		expect( findUrls( 'background: url(a.png)' )[ 0 ].block ).toBeNull();
	} );
} );

describe( 'splitList', () => {
	it( 'splits at the commas outside brackets and strings', () => {
		expect(
			splitList(
				'linear-gradient(#000, #fff), url("a,b.png"), :is(.a, .b)'
			)
		).toEqual( [
			'linear-gradient(#000, #fff)',
			'url("a,b.png")',
			':is(.a, .b)',
		] );
	} );
} );

describe( 'selectorNames', () => {
	document.body.innerHTML = '<section class="hero"></section>';
	const hero = document.querySelector( '.hero' );

	it( 'names an element, or one of its pseudo-elements, and not the other', () => {
		expect( selectorNames( '.a, .hero', hero ) ).toBe( true );
		expect( selectorNames( '.hero::before', hero ) ).toBe( false );
		expect( selectorNames( '.hero::before', hero, '::before' ) ).toBe(
			true
		);
		expect( selectorNames( '.hero:before', hero, '::before' ) ).toBe(
			true
		);
		expect( selectorNames( '.hero::after', hero, '::before' ) ).toBe(
			false
		);
		expect( selectorNames( '.x, ::BEFORE', hero, '::before' ) ).toBe(
			true
		);
	} );

	it( 'names nothing with no selector, or one the browser cannot read', () => {
		expect( selectorNames( null, hero ) ).toBe( false );
		expect( selectorNames( '.hero[', hero ) ).toBe( false );
	} );
} );

describe( 'locateBackground on a pseudo-element', () => {
	afterEach( () => jest.restoreAllMocks() );

	it( 'finds the image in the rule that names the pseudo-element', () => {
		document.body.innerHTML = '<div id="root"></div>';
		const root = document.getElementById( 'root' );
		const map = new DomMap( document );
		root.replaceChildren(
			map.load(
				'<style>.hero { background: url("/img/a.png"); }\n.hero::before { content: ""; background: url("/img/a.png") center; }</style><div class="hero">x</div>'
			)
		);
		const view = document.defaultView;
		const computed = view.getComputedStyle.bind( view );
		jest.spyOn( view, 'getComputedStyle' ).mockImplementation(
			( element, pseudo ) =>
				pseudo
					? { content: '""', backgroundImage: 'url("/img/a.png")' }
					: computed( element )
		);
		const hero = root.querySelector( '.hero' );

		const found = locateBackground( hero, root, map, '::before' );
		expect( found.token.selector ).toBe( '.hero::before' );
		expect( found.named ).toBe( true );
		expect( found.url ).toBe(
			new URL( '/img/a.png', document.baseURI ).href
		);

		// The element's own rule is the element's.
		expect( locateBackground( hero, root, map ).token.selector ).toBe(
			'.hero'
		);
	} );

	it( 'says when the image is only in a rule that shares its file', () => {
		document.body.innerHTML = '<div id="root"></div>';
		const root = document.getElementById( 'root' );
		const map = new DomMap( document );
		root.replaceChildren(
			map.load(
				'<style>.page { --hero: url("/img/a.png"); } .hero { background: var(--hero); }</style><div class="page"><div class="hero">x</div></div>'
			)
		);
		const view = document.defaultView;
		jest.spyOn( view, 'getComputedStyle' ).mockReturnValue( {
			backgroundImage: 'url("/img/a.png")',
		} );

		const found = locateBackground(
			root.querySelector( '.hero' ),
			root,
			map
		);
		expect( found.token.selector ).toBe( '.page' );
		expect( found.named ).toBe( false );
	} );
} );

describe( 'setDeclaration', () => {
	const set = ( css, value = '30% 60%' ) => {
		const [ token ] = findUrls( css );
		const block = token?.block || { start: 0, end: css.length };
		return applyEdit(
			css,
			setDeclaration( css, block, 'background-position', value )
		);
	};

	it( 'replaces the position the rule already has', () => {
		expect(
			set(
				'.a { background-image: url(a.png); background-position: center; }'
			)
		).toBe(
			'.a { background-image: url(a.png); background-position: 30% 60%; }'
		);
	} );

	it( 'keeps an !important on the position it replaces', () => {
		expect(
			set(
				'.a { background: url(a.png); background-position: top !important }'
			)
		).toBe(
			'.a { background: url(a.png); background-position: 30% 60% !important }'
		);
	} );

	it( 'adds a position after a shorthand that would reset one written before it', () => {
		expect(
			set(
				'.a { background-position: top; background: #222 url(a.png) center / cover; padding: 4px; }'
			)
		).toBe(
			'.a { background-position: top; background: #222 url(a.png) center / cover; padding: 4px; background-position: 30% 60%; }'
		);
	} );

	it( 'lays a new declaration out as the one before it', () => {
		expect(
			set(
				'.a {\n\tbackground: url(a.png) center / cover;\n\tpadding: 4px;\n}'
			)
		).toBe(
			'.a {\n\tbackground: url(a.png) center / cover;\n\tpadding: 4px;\n\tbackground-position: 30% 60%;\n}'
		);
	} );

	it( 'ends the last declaration before adding one', () => {
		expect( set( '.a { background: url(a.png) }' ) ).toBe(
			'.a { background: url(a.png); background-position: 30% 60%; }'
		);
	} );

	it( 'writes into a style attribute', () => {
		expect(
			set( "background: url('a.png') center / cover; padding: 4px" )
		).toBe(
			"background: url('a.png') center / cover; padding: 4px; background-position: 30% 60%;"
		);
	} );

	it( 'writes the only declaration of an empty rule or attribute', () => {
		expect(
			applyEdit(
				'.a {}',
				setDeclaration(
					'.a {}',
					{ start: 4, end: 4 },
					'background-position',
					'30% 60%'
				)
			)
		).toBe( '.a { background-position: 30% 60%; }' );
		expect(
			applyEdit(
				'',
				setDeclaration(
					'',
					{ start: 0, end: 0 },
					'background-position',
					'30% 60%'
				)
			)
		).toBe( 'background-position: 30% 60%;' );
	} );

	it( 'passes over a rule nested in the block, and adds to the block’s own declarations', () => {
		expect(
			set(
				'.a { background: url(a.png); &:hover { background-position: top; } }'
			)
		).toBe(
			'.a { background: url(a.png); background-position: 30% 60%; &:hover { background-position: top; } }'
		);
	} );
} );

describe( 'removalOf', () => {
	const remove = ( css ) => {
		const [ token ] = findUrls( css );
		const edit = removalOf( css, token );
		return edit && applyEdit( css, edit );
	};

	it( 'leaves `none` in the image’s place, whatever the declaration holds', () => {
		expect(
			remove( '.a { background: #222 url("a.png") center / cover; }' )
		).toBe( '.a { background: #222 none center / cover; }' );
		expect(
			remove(
				'.a { background-image: linear-gradient(#000, #fff), url(a.png); }'
			)
		).toBe( '.a { background-image: linear-gradient(#000, #fff), none; }' );
		expect( remove( "background-image: url('a.png'); color: red" ) ).toBe(
			'background-image: none; color: red'
		);
		expect( remove( '.page { --hero: url(a.png); }' ) ).toBe(
			'.page { --hero: none; }'
		);
	} );

	it( 'leaves alone an image inside another function', () => {
		expect(
			remove(
				'.a { background-image: image-set(url(a.png) 1x, url(b.png) 2x); }'
			)
		).toBeNull();
	} );
} );

describe( 'focalPointOf and formatPosition', () => {
	afterEach( () => jest.restoreAllMocks() );

	const computed = ( backgroundImage, backgroundPosition ) =>
		jest
			.spyOn( document.defaultView, 'getComputedStyle' )
			.mockReturnValue( { backgroundImage, backgroundPosition } );

	it( 'reads the position of the image’s own layer', () => {
		computed(
			'linear-gradient(rgb(0, 0, 0), rgb(255, 255, 255)), url("a.png")',
			'50% 50%, 25% 80%'
		);

		expect( focalPointOf( document.body ) ).toEqual( { x: 0.25, y: 0.8 } );
	} );

	it( 'reads a position that is not in percentages as the middle', () => {
		computed( 'url("a.png")', '10px 20px' );

		expect( focalPointOf( document.body ) ).toEqual( { x: 0.5, y: 0.5 } );
	} );

	it( 'writes a focal point in percentages to one decimal place', () => {
		expect( formatPosition( { x: 0.3, y: 0.6666 } ) ).toBe( '30% 66.7%' );
		expect( formatPosition( { x: -1, y: 2 } ) ).toBe( '0% 100%' );
	} );
} );
