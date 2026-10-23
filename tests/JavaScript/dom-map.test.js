import {
	ATTR_HOST,
	ATTR_PAD,
	ATTR_RUN,
} from '../../assets/js/src/visual-editor/constants';
import { DomMap } from '../../assets/js/src/visual-editor/dom-map';
import { toggleFormat } from '../../assets/js/src/visual-editor/formatting';

// Loads HTML into a root element, the way the editor does.
const load = ( html ) => {
	document.body.innerHTML = '<div id="root"></div>';
	const root = document.getElementById( 'root' );
	const map = new DomMap( document );
	const content = map.load( html );
	if ( content ) {
		root.replaceChildren( content );
	}

	return { map, root, loaded: !! content };
};

// Writes an element's content and pairs the elements with the tags again.
const save = ( map, root, element, plainSpacesIn ) =>
	map.write( element, plainSpacesIn ) && map.reindex( root );

describe( 'DomMap', () => {
	describe( 'load', () => {
		it( 'parses the HTML without leaving a mark on it', () => {
			const html =
				'<style>.a > b { color: red; }</style>\n<div class="a"><p>Hi <b>there</b></p><img src="a.png"></div>';
			const { root, loaded } = load( html );

			expect( loaded ).toBe( true );
			expect( root.innerHTML ).toBe( html );
		} );

		it( 'pairs every element with its start tag', () => {
			const { map, root } = load(
				'<div><p>One</p><p id="two">Two <b>2</b></p></div>'
			);
			const tag = map.tagOf( root.querySelector( '#two' ) );

			expect( map.html.slice( tag.start, tag.end ) ).toBe(
				'<p id="two">'
			);
			expect( map.tagOf( root.querySelector( 'b' ) ).name ).toBe( 'b' );
		} );

		it( 'keeps scripts in place and inert, and inline handlers under another name', () => {
			window.aicRan = 0;
			const { root } = load(
				'<script>window.aicRan++;</script><a href="#" onclick="window.aicRan++">Go</a>'
			);
			root.querySelector( 'a' ).click();

			expect( window.aicRan ).toBe( 0 );
			expect( root.querySelector( 'script' ).textContent ).toBe(
				'window.aicRan++;'
			);
			expect( root.querySelector( 'a' ).hasAttribute( 'onclick' ) ).toBe(
				false
			);
			expect(
				root
					.querySelector( 'a' )
					.getAttribute( 'data-a8csp-ve-onclick' )
			).toBe( 'window.aicRan++' );
		} );

		it( 'accepts elements the parser adds on its own', () => {
			const { map, root, loaded } = load(
				'<table><tr><td>Cell</td></tr></table>'
			);

			expect( loaded ).toBe( true );
			expect( map.isReal( root.querySelector( 'tbody' ) ) ).toBe( false );
			expect( map.isReal( root.querySelector( 'td' ) ) ).toBe( true );
		} );

		it( 'refuses markup a browser moves around to repair', () => {
			expect(
				load( '<table><tr><div>Stray</div><td>Cell</td></tr></table>' )
					.loaded
			).toBe( false );
			expect( load( '<p><b>one<i>two</b>three</i></p>' ).loaded ).toBe(
				false
			);
		} );

		it( 'refuses a block that uses the reserved attribute prefix', () => {
			expect( load( '<p data-a8csp-ve-host>x</p>' ).loaded ).toBe(
				false
			);
		} );
	} );

	describe( 'findWritable', () => {
		it( 'returns the element itself when its range holds up', () => {
			const { map, root } = load(
				'<div><p>Hi <b>there</b> &amp; you&nbsp;too</p></div>'
			);
			const paragraph = root.querySelector( 'p' );

			expect( map.findWritable( paragraph, root ) ).toBe( paragraph );
		} );

		it( 'climbs past an element that is never closed', () => {
			const { map, root } = load( '<ul><li>One<li>Two</ul>' );
			const [ first ] = root.querySelectorAll( 'li' );

			expect( map.findWritable( first, root ) ).toBe(
				root.querySelector( 'ul' )
			);
		} );

		it( 'climbs past an element the parser added', () => {
			const { map, root } = load(
				'<table><tr><td>Cell</td></tr></table>'
			);

			expect(
				map.findWritable( root.querySelector( 'tbody' ), root )
			).toBe( root.querySelector( 'table' ) );
		} );

		it( 'climbs past a paragraph a browser closed early, to markup it can rewrite as repaired', () => {
			const { map, root } = load(
				'<section><p>One<div>Two</div></p></section>'
			);
			const paragraph = root.querySelector( 'p' );
			const section = root.querySelector( 'section' );

			// The source's `<p>…</p>` holds the div; the parsed paragraph does not.
			expect( map.isWritable( paragraph ) ).toBe( false );
			expect( map.findWritable( paragraph, root ) ).toBe( section );

			paragraph.firstChild.data = 'Uno';
			expect( save( map, root, section ) ).toBe( true );
			expect( map.html ).toBe(
				'<section><p>Uno</p><div>Two</div><p></p></section>'
			);
		} );

		it( 'returns nothing for an element with no content of its own', () => {
			const { map, root } = load( '<p><img src="a.png"></p>' );

			expect( map.isWritable( root.querySelector( 'img' ) ) ).toBe(
				false
			);
		} );
	} );

	describe( 'write', () => {
		it( 'rewrites only the element that changed', () => {
			const html =
				'<style>\n.a > b { color: red; }\n</style>\n\n<div class=\'a\'>\n  <h1 CLASS="t">Old   title</h1>\n  <p>Text&nbsp;&copy; <br/> <img src=a.png /></p>\n</div>\n';
			const { map, root } = load( html );
			const heading = root.querySelector( 'h1' );

			expect( map.findWritable( heading, root ) ).toBe( heading );
			heading.firstChild.data = 'New title';

			expect( save( map, root, heading ) ).toBe( true );
			expect( map.html ).toBe(
				html.replace( 'Old   title', 'New title' )
			);
		} );

		it( 'reports no change when the content is what the source already has', () => {
			const { map, root } = load( '<p>Same</p>' );

			expect( map.write( root.querySelector( 'p' ) ) ).toBe( false );
		} );

		it( 'escapes what is typed', () => {
			const { map, root } = load( '<p>x</p>' );
			const paragraph = root.querySelector( 'p' );
			paragraph.firstChild.data = '1 < 2 & <b>not bold</b>';
			save( map, root, paragraph );

			expect( map.html ).toBe(
				'<p>1 &lt; 2 &amp; &lt;b&gt;not bold&lt;/b&gt;</p>'
			);
		} );

		it( 'keeps the elements paired with their tags as the structure changes', () => {
			const { map, root } = load(
				'<div><p>One two</p><p id="after">Three</p></div>'
			);
			const [ first, second ] = root.querySelectorAll( 'p' );

			toggleFormat( first, { start: 4, end: 7 }, 'bold' );
			const strong = first.querySelector( 'strong' );

			expect( save( map, root, first ) ).toBe( true );
			expect( map.html ).toBe(
				'<div><p>One <strong>two</strong></p><p id="after">Three</p></div>'
			);
			expect( map.tagOf( strong ).name ).toBe( 'strong' );

			const tag = map.tagOf( second );
			expect( map.html.slice( tag.start, tag.end ) ).toBe(
				'<p id="after">'
			);

			// A second write lands on the moved range.
			second.firstChild.data = 'Four';
			expect( save( map, root, second ) ).toBe( true );
			expect( map.html ).toBe(
				'<div><p>One <strong>two</strong></p><p id="after">Four</p></div>'
			);
		} );

		it( 'leaves out the marks of the editor', () => {
			const { map, root } = load(
				'<a class="btn" href="/x">Order <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></a>'
			);
			const link = root.querySelector( 'a' );
			expect( map.findWritable( link, root ) ).toBe( link );

			const run = document.createElement( 'span' );
			run.setAttribute( ATTR_RUN, '' );
			run.setAttribute( ATTR_HOST, '' );
			run.setAttribute( 'contenteditable', 'true' );
			link.firstChild.before( run );
			run.appendChild( link.childNodes[ 1 ] );
			run.firstChild.data = 'Order now ';

			expect( save( map, root, link ) ).toBe( true );
			expect( map.html ).toBe(
				'<a class="btn" href="/x">Order now <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></a>'
			);
			// The wrapper is the editor's, and has no tag in the source.
			expect( map.isReal( run ) ).toBe( false );
			expect( map.tagOf( root.querySelector( 'path' ) ).name ).toBe(
				'path'
			);
		} );

		it( 'leaves out the break that holds the caret, and stays paired with the tags', () => {
			const { map, root } = load( '<h3>Old</h3><p>After</p>' );
			const heading = root.querySelector( 'h3' );
			expect( map.findWritable( heading, root ) ).toBe( heading );

			const pad = document.createElement( 'br' );
			pad.setAttribute( ATTR_PAD, '' );
			heading.replaceChildren( pad );

			expect( save( map, root, heading ) ).toBe( true );
			expect( map.html ).toBe( '<h3></h3><p>After</p>' );
			expect( map.tagOf( root.querySelector( 'p' ) ).name ).toBe( 'p' );
		} );

		it( 'gives inline handlers their names back', () => {
			const { map, root } = load(
				'<p>Call <a href="#" onclick="track()" class="x">us</a> now</p>'
			);
			const paragraph = root.querySelector( 'p' );
			expect( map.findWritable( paragraph, root ) ).toBe( paragraph );

			paragraph.lastChild.data = ' today';
			save( map, root, paragraph );

			expect( map.html ).toBe(
				'<p>Call <a href="#" onclick="track()" class="x">us</a> today</p>'
			);
		} );

		it( 'writes the tags of the source as the source has them', () => {
			const html =
				"<p CLASS='lead'>Old <A HREF=/x  onclick='go()'>link</A> <img src=a.png alt /> <br/>&copy; text</p>";
			const { map, root } = load( html );
			const paragraph = root.querySelector( 'p' );
			expect( map.findWritable( paragraph, root ) ).toBe( paragraph );

			paragraph.firstChild.data = 'New ';
			save( map, root, paragraph );

			expect( map.html ).toBe(
				"<p CLASS='lead'>New <A HREF=/x  onclick='go()'>link</A> <img src=a.png alt /> <br/>© text</p>"
			);
		} );

		it( 'takes nothing from the page that the source does not have', () => {
			const { map, root } = load(
				'<section><details><summary>Q</summary><p>A</p></details><p id="t">Text <a href="/x">link</a></p></section>'
			);
			const section = root.querySelector( 'section' );
			const text = root.querySelector( '#t' );
			expect( map.findWritable( text, root ) ).toBe( text );

			// What a browser extension, or a click on the summary, leaves behind.
			root.querySelector( 'details' ).setAttribute( 'open', '' );
			text.setAttribute( 'data-gramm', 'false' );
			text.setAttribute( 'style', '' );
			text.querySelector( 'a' ).setAttribute(
				'data-darkreader-inline-color',
				''
			);
			const stray = document.createElement( 'grammarly-mark' );
			stray.textContent = 'checked ';
			text.prepend( stray );
			text.lastChild.before( 'new ' );

			expect( map.write( text ) ).toBe( true );
			expect( map.html ).toContain(
				'<p id="t">checked Text new <a href="/x">link</a></p>'
			);

			// And the same from further up, were the change written there.
			expect( map.serialize( section ) ).toBe(
				'<details><summary>Q</summary><p>A</p></details><p id="t">checked Text new <a href="/x">link</a></p>'
			);
		} );

		it( 'writes typed non-breaking spaces as spaces, where asked to', () => {
			const { map, root } = load(
				'<div><p>One</p><p>Two&nbsp;2</p></div>'
			);
			const [ first, second ] = root.querySelectorAll( 'p' );
			first.firstChild.data = 'One\u00a0more\u00a0';
			second.firstChild.data = 'Two\u00a02!';

			save( map, root, first, first );
			save( map, root, second );

			expect( map.html ).toBe(
				'<div><p>One more </p><p>Two&nbsp;2!</p></div>'
			);
		} );

		it( 'writes the line feed a browser would drop from the start of a pre', () => {
			const { map, root } = load(
				'<div><pre>\n\nfirst</pre><pre>\nsecond</pre></div>'
			);
			const [ first, second ] = root.querySelectorAll( 'pre' );

			expect( first.textContent ).toBe( '\nfirst' );
			expect( map.findWritable( first, root ) ).toBe( first );
			expect( map.findWritable( second, root ) ).toBe( second );
			expect( map.serialize( root.querySelector( 'div' ) ) ).toBe(
				'<pre>\n\nfirst</pre><pre>second</pre>'
			);

			first.firstChild.data = '\nfirst!';
			save( map, root, first );
			expect( map.html ).toBe(
				'<div><pre>\n\nfirst!</pre><pre>\nsecond</pre></div>'
			);
		} );

		it( 'copies the text of a script and a style', () => {
			const html =
				'<div><style>a > b { content: "&amp;"; }</style><script>if ( a < b && c ) { d(); }</script><p>x</p></div>';
			const { map, root } = load( html );
			const block = root.querySelector( 'div' );
			expect( map.findWritable( block, root ) ).toBe( block );

			root.querySelector( 'p' ).firstChild.data = 'y';
			save( map, root, block );

			expect( map.html ).toBe( html.replace( '<p>x</p>', '<p>y</p>' ) );
		} );

		it( 'writes out an element the parser added, which is then in the source', () => {
			const { map, root } = load(
				'<table><tr><td>Cell</td></tr></table>'
			);
			const table = root.querySelector( 'table' );
			root.querySelector( 'td' ).firstChild.data = 'New';

			expect( save( map, root, table ) ).toBe( true );
			expect( map.html ).toBe(
				'<table><tbody><tr><td>New</td></tr></tbody></table>'
			);
			expect( map.isReal( root.querySelector( 'tbody' ) ) ).toBe( true );
		} );
	} );

	describe( 'setAttributes', () => {
		it( 'changes the element and its tag', () => {
			const { map, root } = load(
				'<p>See <a class="x" href="/old/">this</a> and <a href="/other/">that</a></p>'
			);
			const link = root.querySelector( 'a' );

			expect(
				map.setAttributes( link, { href: '/new/', target: '_blank' } )
			).toBe( true );
			expect( map.html ).toBe(
				'<p>See <a class="x" href="/new/" target="_blank">this</a> and <a href="/other/">that</a></p>'
			);
			expect( link.getAttribute( 'href' ) ).toBe( '/new/' );
			expect( link.getAttribute( 'target' ) ).toBe( '_blank' );

			expect( map.setAttributes( link, { target: null } ) ).toBe( true );
			expect( map.html ).toContain(
				'<a class="x" href="/new/">this</a>'
			);
			expect( link.hasAttribute( 'target' ) ).toBe( false );
		} );

		it( 'changes an element that is being edited', () => {
			const { map, root } = load( '<a href="/old/">Go</a>' );
			const link = root.querySelector( 'a' );
			link.setAttribute( ATTR_HOST, '' );
			link.setAttribute( 'contenteditable', 'true' );

			expect( map.setAttributes( link, { href: '/new/' } ) ).toBe( true );
			expect( map.html ).toBe( '<a href="/new/">Go</a>' );
		} );

		it( 'changes SVG and table elements, whose tags do not parse on their own', () => {
			const { map, root } = load(
				'<svg viewBox="0 0 1 1"><image href="a.png" preserveAspectRatio="none"/></svg><table><tr><td class="a">x</td></tr></table>'
			);

			expect(
				map.setAttributes( root.querySelector( 'image' ), {
					href: 'b.png',
				} )
			).toBe( true );
			expect(
				map.setAttributes( root.querySelector( 'td' ), { class: 'b' } )
			).toBe( true );
			expect( map.html ).toBe(
				'<svg viewBox="0 0 1 1"><image href="b.png" preserveAspectRatio="none"/></svg><table><tr><td class="b">x</td></tr></table>'
			);
		} );

		it( 'goes by the tag, not by what the page has since done to the element', () => {
			const { map, root } = load(
				'<details><summary>Q</summary>A</details>'
			);
			const details = root.querySelector( 'details' );
			details.setAttribute( 'open', '' );
			details.setAttribute( 'style', '' );

			expect( map.setAttributes( details, { class: 'x' } ) ).toBe( true );
			expect( map.html ).toBe(
				'<details class="x"><summary>Q</summary>A</details>'
			);
		} );

		it( 'refuses an element with no tag in the source', () => {
			const { map, root } = load( '<table><tr><td>x</td></tr></table>' );

			expect( map.hasEditableTag( root.querySelector( 'tbody' ) ) ).toBe(
				false
			);
			expect(
				map.setAttributes( root.querySelector( 'tbody' ), {
					class: 'x',
				} )
			).toBe( false );
			expect( map.hasEditableTag( root.querySelector( 'td' ) ) ).toBe(
				true
			);
		} );

		it( 'keeps an inline handler on the tag it changes', () => {
			const { map, root } = load(
				'<a href="/old/" onclick="track()">Go</a>'
			);

			expect(
				map.setAttributes( root.querySelector( 'a' ), {
					href: '/new/',
				} )
			).toBe( true );
			expect( map.html ).toBe(
				'<a href="/new/" onclick="track()">Go</a>'
			);
		} );
	} );

	describe( 'raw text', () => {
		it( 'reads and replaces part of a style', () => {
			const { map, root } = load(
				'<style>.hero { background: url("a.png"); }</style><div class="hero">x</div>'
			);
			const style = root.querySelector( 'style' );
			const text = map.rawText( style );
			const start = text.indexOf( '"a.png"' );

			expect(
				map.replaceRawText( style, start, start + 7, '"b.png"' )
			).toBe( true );
			expect( map.html ).toBe(
				'<style>.hero { background: url("b.png"); }</style><div class="hero">x</div>'
			);
			expect( style.textContent ).toBe(
				'.hero { background: url("b.png"); }'
			);
			expect( map.tagOf( root.querySelector( '.hero' ) ).name ).toBe(
				'div'
			);
		} );
	} );
} );
