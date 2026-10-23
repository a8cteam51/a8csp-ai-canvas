import { ATTR_EMPTY } from '../../assets/js/src/visual-editor/constants';
import {
	findRuns,
	isSameTarget,
	labelFor,
	nodesOf,
	resolveTarget,
} from '../../assets/js/src/visual-editor/targets';

const rootOf = ( html ) => {
	document.body.innerHTML = `<div id="root">${ html }</div>`;
	return document.getElementById( 'root' );
};

const resolve = ( root, selector ) =>
	resolveTarget( root, root.querySelector( selector ) );

const runText = ( run ) =>
	nodesOf( run )
		.map( ( node ) => node.textContent )
		.join( '' );

describe( 'resolveTarget', () => {
	it( 'edits a paragraph from a click on the paragraph', () => {
		const root = rootOf(
			'<section><p>Hello <strong>there</strong></p></section>'
		);
		const target = resolve( root, 'p' );

		expect( target.kind ).toBe( 'text' );
		expect( target.host ).toBe( root.querySelector( 'p' ) );
		expect( target.run ).toBeNull();
	} );

	it( 'edits the whole sentence from a click on a format inside it', () => {
		const root = rootOf(
			'<p>Hello <strong>there</strong> and <a href="/x">here</a></p>'
		);
		const paragraph = root.querySelector( 'p' );

		expect( resolve( root, 'strong' ).host ).toBe( paragraph );
		expect( resolve( root, 'a' ).host ).toBe( paragraph );
	} );

	it( 'edits a paragraph that is all one format', () => {
		const root = rootOf( '<p><strong>All bold</strong></p>' );

		expect( resolve( root, 'strong' ).host ).toBe(
			root.querySelector( 'p' )
		);
		expect( resolve( root, 'p' ).host ).toBe( root.querySelector( 'p' ) );
	} );

	it( 'edits a heading with a styled word as one piece', () => {
		const root = rootOf(
			'<h1>Bake days, <span class="accent">made simple</span></h1>'
		);

		expect( resolve( root, '.accent' ).host ).toBe(
			root.querySelector( 'h1' )
		);
	} );

	it( 'edits one button, not the row of buttons', () => {
		const root = rootOf(
			'<div class="actions"><a class="btn" href="/a">Order</a> <a class="btn" href="/b"><span>Menu</span></a></div>'
		);
		const [ first, second ] = root.querySelectorAll( 'a' );

		expect( resolveTarget( root, first ).host ).toBe( first );
		expect( resolveTarget( root, first ).link ).toBe( first );
		expect( resolveTarget( root, second ).host ).toBe( second.firstChild );
		expect( resolveTarget( root, second.firstChild ).link ).toBe( second );
	} );

	it( 'frames the button a piece of text is the whole label of', () => {
		const root = rootOf(
			'<a id="one" href="/a"><span>Menu</span></a>' +
				'<a id="two" href="/b">Order <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></a>' +
				'<a id="three" href="/c"><span class="title">Title</span><span class="sub">Subtitle</span></a>' +
				'<p>Read <a id="four" href="/d">more</a></p>'
		);

		expect( resolve( root, '#one span' ).frame ).toBe(
			root.querySelector( '#one' )
		);
		expect( resolve( root, '#two' ).frame ).toBe(
			root.querySelector( '#two' )
		);
		expect( resolve( root, '#three .title' ).frame ).toBeNull();
		expect( resolve( root, '#four' ).frame ).toBeNull();
	} );

	it( 'edits each of two styled spans on its own', () => {
		const root = rootOf(
			'<div class="stat"><span class="num">120+</span><span class="label">Loaves</span></div>'
		);

		expect( resolve( root, '.num' ).host ).toBe(
			root.querySelector( '.num' )
		);
		expect( resolve( root, '.label' ).host ).toBe(
			root.querySelector( '.label' )
		);
		expect( resolve( root, '.stat' ) ).toBeNull();
	} );

	it( 'follows a wrapper down to the element that holds its text', () => {
		const root = rootOf(
			'<ul><li><span>Delivery windows</span></li></ul>'
		);

		expect( resolve( root, 'li' ).host ).toBe(
			root.querySelector( 'span' )
		);
	} );

	it( 'does not edit a layout wrapper', () => {
		const root = rootOf( '<section><h2>Title</h2><p>Text</p></section>' );

		expect( resolve( root, 'section' ) ).toBeNull();
	} );

	it( 'edits the text beside an icon as a run', () => {
		const root = rootOf(
			'<a class="btn" href="/order/">Order a box <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></a>'
		);
		const link = root.querySelector( 'a' );
		const target = resolveTarget( root, link );

		expect( target.kind ).toBe( 'text' );
		expect( target.host ).toBeNull();
		expect( target.container ).toBe( link );
		expect( runText( target.run ) ).toBe( 'Order a box ' );
		expect( target.link ).toBe( link );
	} );

	it( 'edits the text of a button from a click on its icon', () => {
		const root = rootOf(
			'<a class="btn" href="/order/">Order a box <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></a>'
		);
		const target = resolve( root, 'path' );

		expect( target.kind ).toBe( 'text' );
		expect( runText( target.run ) ).toBe( 'Order a box ' );
	} );

	it( 'edits the text of a list item apart from the list nested in it', () => {
		const root = rootOf(
			'<ul><li id="outer">Pause or <em>skip</em> any week\n<ul><li>No fees</li></ul></li></ul>'
		);
		const target = resolve( root, '#outer' );

		expect( target.container ).toBe( root.querySelector( '#outer' ) );
		expect( runText( target.run ) ).toBe( 'Pause or skip any week\n' );
		expect( resolve( root, 'em' ).run.first ).toBe( target.run.first );
		expect( resolve( root, 'li li' ).host ).toBe(
			root.querySelector( 'li li' )
		);
	} );

	it( 'edits the text of a label apart from its checkbox', () => {
		const root = rootOf(
			'<label><input type="checkbox"> I agree to the terms</label>'
		);
		const target = resolve( root, 'label' );

		expect( runText( target.run ) ).toBe( ' I agree to the terms' );
		expect( resolve( root, 'input' ) ).toBeNull();
	} );

	it( 'selects an image, with the link around it', () => {
		const root = rootOf(
			'<a href="/g/"><img src="a.png" alt=""></a><img id="bare" src="b.png">'
		);

		expect( resolve( root, 'a img' ) ).toEqual( {
			kind: 'image',
			element: root.querySelector( 'a img' ),
			link: root.querySelector( 'a' ),
		} );
		expect( resolve( root, '#bare' ).link ).toBeNull();
	} );

	it( 'selects the image of a picture', () => {
		const root = rootOf(
			'<picture><source srcset="a.png"><img src="b.png"></picture>'
		);

		expect( resolve( root, 'picture' ).element ).toBe(
			root.querySelector( 'img' )
		);
	} );

	it( 'selects a link that holds only an icon', () => {
		const root = rootOf(
			'<a href="https://example.com/"><svg><path d=""/></svg></a>'
		);

		expect( resolve( root, 'path' ) ).toEqual( {
			kind: 'link',
			element: root.querySelector( 'a' ),
			link: root.querySelector( 'a' ),
		} );
	} );

	it( 'selects an element with a background image', () => {
		const root = rootOf(
			'<section style="background-image: url(hero.png)"><div class="wrap"><h1>Title</h1></div></section>'
		);
		const target = resolveTarget( root, root.querySelector( '.wrap' ), {
			x: 4,
			y: 5,
		} );

		expect( target ).toEqual( {
			kind: 'background',
			element: root.querySelector( 'section' ),
			point: { x: 4, y: 5 },
		} );
	} );

	it( 'edits nothing from a click on a form field, a script or the root', () => {
		const root = rootOf(
			'<form><input type="email"><textarea>x</textarea><select><option>One</option></select></form><script>var a;</script>'
		);

		expect( resolve( root, 'input' ) ).toBeNull();
		expect( resolve( root, 'textarea' ) ).toBeNull();
		expect( resolve( root, 'option' ) ).toBeNull();
		expect( resolve( root, 'script' ) ).toBeNull();
		expect( resolveTarget( root, root ) ).toBeNull();
		expect( resolveTarget( root, document.body ) ).toBeNull();
	} );

	it( 'still edits an element whose text was deleted', () => {
		const root = rootOf( '<h2></h2>' );
		const heading = root.querySelector( 'h2' );

		expect( resolveTarget( root, heading ) ).toBeNull();

		heading.setAttribute( ATTR_EMPTY, '' );
		expect( resolveTarget( root, heading ).host ).toBe( heading );
	} );
} );

describe( 'findRuns', () => {
	it( 'has no run for indentation and comments alone', () => {
		const root = rootOf(
			'<div id="c">\n  <!-- note -->\n  <p>block</p>\n</div>'
		);

		expect( findRuns( root.querySelector( '#c' ) ) ).toEqual( [] );
	} );

	it( 'splits the content at everything that is not text', () => {
		const root = rootOf(
			'<div id="c">\n  One <b>two</b>\n  <p>block</p>\n  <!-- note -->\n  three<br>four<img src="x">five\n</div>'
		);

		// A run keeps its text nodes whole; only the nodes that are nothing but indentation are left out.
		expect( findRuns( root.querySelector( '#c' ) ).map( runText ) ).toEqual(
			[ '\n  One two', '\n  threefour', 'five\n' ]
		);
	} );
} );

describe( 'isSameTarget', () => {
	it( 'compares what the targets edit', () => {
		const root = rootOf( '<p>One</p><p>Two <img src="a.png"> three</p>' );
		const [ first, second ] = root.querySelectorAll( 'p' );

		expect(
			isSameTarget(
				resolveTarget( root, first ),
				resolveTarget( root, first )
			)
		).toBe( true );
		expect(
			isSameTarget(
				resolveTarget( root, first ),
				resolveTarget( root, second )
			)
		).toBe( false );
		expect(
			isSameTarget(
				resolveTarget( root, second ),
				resolveTarget( root, second )
			)
		).toBe( true );
		expect(
			isSameTarget( resolve( root, 'img' ), resolve( root, 'img' ) )
		).toBe( true );
		expect(
			isSameTarget( resolve( root, 'img' ), resolveTarget( root, first ) )
		).toBe( false );
		expect( isSameTarget( null, resolveTarget( root, first ) ) ).toBe(
			false
		);
	} );
} );

describe( 'labelFor', () => {
	const label = ( html, selector ) => {
		const root = rootOf( html );
		return labelFor( resolve( root, selector ), root );
	};

	it( 'names text by what it is', () => {
		expect( label( '<h2>Title</h2>', 'h2' ) ).toBe( 'Heading' );
		expect( label( '<p>Text</p>', 'p' ) ).toBe( 'Text' );
		expect( label( '<ul><li><span>Item</span></li></ul>', 'li' ) ).toBe(
			'List item'
		);
		expect(
			label( '<ul><li><h3>Title</h3><p>Body</p></li></ul>', 'p' )
		).toBe( 'Text' );
		expect( label( '<blockquote>Quote</blockquote>', 'blockquote' ) ).toBe(
			'Quote'
		);
		expect(
			label(
				'<figure><figcaption>Caption</figcaption></figure>',
				'figcaption'
			)
		).toBe( 'Caption' );
		expect( label( '<table><tr><td>Cell</td></tr></table>', 'td' ) ).toBe(
			'Table cell'
		);
		expect( label( '<label>Email <input></label>', 'label' ) ).toBe(
			'Label'
		);
	} );

	it( 'tells a button from a link by how it is drawn', () => {
		expect( label( '<button>Go</button>', 'button' ) ).toBe( 'Button' );
		expect( label( '<nav><a href="/x">Go</a></nav>', 'a' ) ).toBe( 'Link' );
		expect(
			label(
				'<div><a href="/x" style="display: inline-block; padding: 8px 16px; background-color: rgb(200, 0, 0);">Go</a></div>',
				'a'
			)
		).toBe( 'Button' );
		expect(
			label( '<div><a href="/x" role="button">Go</a></div>', 'a' )
		).toBe( 'Button' );
	} );

	it( 'names images and backgrounds', () => {
		expect( label( '<img src="a.png">', 'img' ) ).toBe( 'Image' );
		expect(
			label(
				'<section style="background-image: url(a.png)"><div></div></section>',
				'div'
			)
		).toBe( 'Background image' );
	} );
} );
