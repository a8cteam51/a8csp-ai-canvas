import {
	addLink,
	cleanForSave,
	getSelectionOffsets,
	hasFormat,
	linkAt,
	markPadding,
	rangeFromOffsets,
	removeLink,
	removeStrays,
	setSelectionOffsets,
	toggleFormat,
	wasMade,
	wordAt,
} from '../../assets/js/src/visual-editor/formatting';

const hostOf = ( html ) => {
	document.body.innerHTML = `<div id="host">${ html }</div>`;
	return document.getElementById( 'host' );
};

// The offsets of a piece of the host's text.
const span = ( host, text ) => {
	const start = host.textContent.indexOf( text );
	return { start, end: start + text.length };
};

describe( 'selection offsets', () => {
	it( 'counts characters across elements', () => {
		const host = hostOf( 'One <b>two</b> three' );
		const range = rangeFromOffsets( host, 5, 10 );

		expect( range.toString() ).toBe( 'wo th' );
		expect( range.startContainer.data ).toBe( 'two' );
		expect( range.endContainer.data ).toBe( ' three' );
	} );

	it( 'keeps a caret with the text it follows', () => {
		const host = hostOf( 'One <b>two</b> three' );
		const range = rangeFromOffsets( host, 7, 7 );

		expect( range.collapsed ).toBe( true );
		expect( range.startContainer.data ).toBe( 'two' );
		expect( range.startOffset ).toBe( 3 );
	} );

	it( 'starts a selection at the text it covers', () => {
		const host = hostOf( 'One <b>two</b> three' );
		const range = rangeFromOffsets( host, 4, 7 );

		expect( range.startContainer.data ).toBe( 'two' );
		expect( range.startOffset ).toBe( 0 );
		expect( range.endContainer.data ).toBe( 'two' );
		expect( range.endOffset ).toBe( 3 );
	} );

	it( 'round-trips the selection', () => {
		const host = hostOf( 'One <b>two</b> three' );
		setSelectionOffsets( host, 2, 9 );

		expect( getSelectionOffsets( host ) ).toEqual( { start: 2, end: 9 } );
		expect( document.getSelection().toString() ).toBe( 'e two t' );
	} );

	it( 'reports nothing for a selection outside the host', () => {
		const host = hostOf( 'One' );
		const other = document.createElement( 'p' );
		other.textContent = 'Other';
		document.body.appendChild( other );
		document.getSelection().selectAllChildren( other );

		expect( getSelectionOffsets( host ) ).toBeNull();
	} );
} );

describe( 'wordAt', () => {
	it( 'finds the word the caret is in or touching', () => {
		const host = hostOf( 'Fresh sourdough, daily' );

		expect( wordAt( host, 8 ) ).toEqual( span( host, 'sourdough' ) );
		expect( wordAt( host, 15 ) ).toEqual( span( host, 'sourdough' ) );
		expect( wordAt( host, 6 ) ).toEqual( span( host, 'sourdough' ) );
	} );

	it( 'falls back to the whole text, without the space around it', () => {
		const host = hostOf( '\n  One  two \n' );

		expect( wordAt( host, 7 ) ).toEqual( { start: 3, end: 11 } );
	} );
} );

describe( 'toggleFormat', () => {
	it( 'makes a stretch of text bold', () => {
		const host = hostOf( 'Fresh sourdough daily' );

		expect( toggleFormat( host, span( host, 'sourdough' ), 'bold' ) ).toBe(
			true
		);
		expect( host.innerHTML ).toBe(
			'Fresh <strong>sourdough</strong> daily'
		);
		expect( wasMade( host.querySelector( 'strong' ) ) ).toBe( true );
	} );

	it( 'makes a stretch of text italic', () => {
		const host = hostOf( 'Fresh sourdough daily' );
		toggleFormat( host, span( host, 'daily' ), 'italic' );

		expect( host.innerHTML ).toBe( 'Fresh sourdough <em>daily</em>' );
	} );

	it( 'does nothing without a stretch of text', () => {
		const host = hostOf( 'Fresh' );

		expect( toggleFormat( host, { start: 2, end: 2 }, 'bold' ) ).toBe(
			false
		);
		expect( host.innerHTML ).toBe( 'Fresh' );
	} );

	it( 'removes the format from a whole element', () => {
		const host = hostOf( 'Fresh <strong>sourdough</strong> daily' );
		toggleFormat( host, span( host, 'sourdough' ), 'bold' );

		expect( host.innerHTML ).toBe( 'Fresh sourdough daily' );
		expect( host.childNodes ).toHaveLength( 1 );
	} );

	it( 'removes the format from the middle of an element', () => {
		const host = hostOf( '<strong>one two three</strong>' );
		toggleFormat( host, span( host, 'two' ), 'bold' );

		expect( host.innerHTML ).toBe(
			'<strong>one </strong>two<strong> three</strong>'
		);
	} );

	it( 'recognizes b and i as bold and italic', () => {
		const host = hostOf( '<b>one</b> <i>two</i>' );

		expect( hasFormat( host, span( host, 'one' ), 'bold' ) ).toBe( true );
		expect( hasFormat( host, span( host, 'two' ), 'italic' ) ).toBe( true );

		toggleFormat( host, span( host, 'one' ), 'bold' );
		expect( host.innerHTML ).toBe( 'one <i>two</i>' );
	} );

	it( 'extends the format over text that only partly has it', () => {
		const host = hostOf( 'one <strong>two</strong> three' );
		toggleFormat( host, span( host, 'one two three' ), 'bold' );

		expect( host.innerHTML ).toBe( '<strong>one two three</strong>' );
	} );

	it( 'formats inside the elements a stretch crosses', () => {
		const host = hostOf( 'Read <a href="/x">our story</a> today' );
		toggleFormat( host, span( host, 'our story today' ), 'bold' );

		expect( host.innerHTML ).toBe(
			'Read <a href="/x"><strong>our story</strong></a><strong> today</strong>'
		);
	} );

	it( 'splits the elements between the format and the text it is removed from', () => {
		const host = hostOf(
			'<strong>aa<span class="x" id="keep">bb cc dd</span>ee</strong>'
		);
		toggleFormat( host, span( host, 'cc' ), 'bold' );

		expect( host.innerHTML ).toBe(
			'<strong>aa<span class="x">bb </span></strong><span class="x" id="keep">cc</span><strong><span class="x"> dd</span>ee</strong>'
		);
	} );

	it( 'leaves a format outside the host alone', () => {
		document.body.innerHTML =
			'<strong><span id="host">one two</span></strong>';
		const host = document.getElementById( 'host' );

		expect( hasFormat( host, span( host, 'one' ), 'bold' ) ).toBe( false );
		toggleFormat( host, span( host, 'one' ), 'bold' );
		expect( host.innerHTML ).toBe( '<strong>one</strong> two' );
	} );

	it( 'keeps both formats on text that has both', () => {
		const host = hostOf( 'one two' );
		toggleFormat( host, span( host, 'two' ), 'bold' );
		toggleFormat( host, span( host, 'two' ), 'italic' );

		expect( host.innerHTML ).toBe( 'one <strong><em>two</em></strong>' );

		toggleFormat( host, span( host, 'two' ), 'bold' );
		expect( host.innerHTML ).toBe( 'one <em>two</em>' );
	} );

	it( 'reports the format at a caret', () => {
		const host = hostOf( 'one <strong>two</strong> three' );

		expect( hasFormat( host, { start: 5, end: 5 }, 'bold' ) ).toBe( true );
		expect( hasFormat( host, { start: 2, end: 2 }, 'bold' ) ).toBe( false );
	} );
} );

describe( 'links', () => {
	it( 'wraps a stretch of text in a link', () => {
		const host = hostOf( 'Read our story today' );
		const link = addLink( host, span( host, 'our story' ), {
			href: '/story/',
		} );

		expect( host.innerHTML ).toBe(
			'Read <a href="/story/">our story</a> today'
		);
		expect( wasMade( link ) ).toBe( true );
	} );

	it( 'puts one link around text that holds a format', () => {
		const host = hostOf( 'Read <strong>our</strong> story today' );
		addLink( host, span( host, 'our story' ), { href: '/story/' } );

		expect( host.innerHTML ).toBe(
			'Read <a href="/story/"><strong>our</strong> story</a> today'
		);
	} );

	it( 'links inside a format it does not cover', () => {
		const host = hostOf( '<strong>one two three</strong>' );
		addLink( host, span( host, 'two' ), { href: '/two/' } );

		expect( host.innerHTML ).toBe(
			'<strong>one <a href="/two/">two</a> three</strong>'
		);
	} );

	it( 'replaces a link that is already there', () => {
		const host = hostOf( 'Read <a href="/old/">our story</a> today' );
		addLink( host, span( host, 'our story today' ), { href: '/new/' } );

		expect( host.innerHTML ).toBe(
			'Read <a href="/new/">our story today</a>'
		);
	} );

	it( 'sets every attribute it is given', () => {
		const host = hostOf( 'Go' );
		addLink(
			host,
			{ start: 0, end: 2 },
			{
				href: 'https://example.com/',
				target: '_blank',
				rel: 'noopener',
			}
		);

		expect( host.innerHTML ).toBe(
			'<a href="https://example.com/" target="_blank" rel="noopener">Go</a>'
		);
	} );

	it( 'finds the link a stretch of text is in', () => {
		const host = hostOf( 'Read <a href="/x">our story</a> today' );
		const link = host.querySelector( 'a' );

		expect( linkAt( host, { start: 7, end: 7 } ) ).toBe( link );
		expect( linkAt( host, span( host, 'story' ) ) ).toBe( link );
		expect( linkAt( host, { start: 2, end: 2 } ) ).toBeNull();
		expect( linkAt( host, span( host, 'story today' ) ) ).toBeNull();
	} );

	it( 'removes a link and keeps its text', () => {
		const host = hostOf( 'Read <a href="/x">our <em>story</em></a> today' );
		removeLink( host.querySelector( 'a' ) );

		expect( host.innerHTML ).toBe( 'Read our <em>story</em> today' );
	} );
} );

describe( 'removeStrays', () => {
	it( 'unwraps what the browser added and keeps what was there', () => {
		const host = hostOf(
			'One <span class="accent">two</span> <span style="font-size: 16px;">three</span> <font color="red">four</font><br>'
		);
		const original = host.querySelector( '.accent' );

		expect(
			removeStrays( host, ( element ) => element === original )
		).toBe( true );
		expect( host.innerHTML ).toBe(
			'One <span class="accent">two</span> three four<br>'
		);
	} );

	it( 'keeps a carried-over format without its inline style', () => {
		const host = hostOf( '<strong style="font-weight: 700;">one</strong>' );
		removeStrays( host, () => false );

		expect( host.innerHTML ).toBe( '<strong>one</strong>' );
		expect( wasMade( host.firstChild ) ).toBe( true );
	} );

	it( 'keeps the elements the formatting functions made', () => {
		const host = hostOf( '<span class="x">one two</span>' );
		const original = host.firstChild;
		toggleFormat( host, span( host, 'two' ), 'bold' );

		expect(
			removeStrays(
				host,
				( element ) => element === original || wasMade( element )
			)
		).toBe( false );
		expect( host.innerHTML ).toBe(
			'<span class="x">one <strong>two</strong></span>'
		);
	} );
} );

describe( 'markPadding', () => {
	const marked = ( html ) => {
		const host = hostOf( html );
		markPadding( host );
		return host.innerHTML;
	};

	it( 'marks the break a browser keeps at the end', () => {
		expect( marked( 'One<br>' ) ).toBe( 'One<br data-a8csp-ve-pad="">' );
		expect( marked( '<br>' ) ).toBe( '<br data-a8csp-ve-pad="">' );
	} );

	it( 'leaves a line break the text ends on', () => {
		expect( marked( 'One<br><br>' ) ).toBe( 'One<br><br>' );
		expect( marked( 'One<br>two' ) ).toBe( 'One<br>two' );
	} );

	it( 'unmarks a break that text now follows', () => {
		const host = hostOf( 'One<br>' );
		markPadding( host );
		host.append( 'two' );
		markPadding( host );

		expect( host.innerHTML ).toBe( 'One<br>two' );
	} );
} );

describe( 'cleanForSave', () => {
	const cleaned = ( html, keepsHardSpaces = false ) => {
		const host = hostOf( html );
		cleanForSave( host, keepsHardSpaces );
		return host.innerHTML;
	};

	it( 'turns typed non-breaking spaces into spaces', () => {
		expect( cleaned( 'One&nbsp;<b>two&nbsp;</b>' ) ).toBe(
			'One <b>two </b>'
		);
	} );

	it( 'keeps non-breaking spaces the text already had', () => {
		expect( cleaned( 'Before 8&nbsp;am', true ) ).toBe(
			'Before 8&nbsp;am'
		);
	} );
} );
