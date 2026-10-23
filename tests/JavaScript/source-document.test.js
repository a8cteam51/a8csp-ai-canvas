import {
	SourceDocument,
	escapeAttribute,
} from '../../assets/js/src/visual-editor/source-document';

const tagNamed = ( source, name, nth = 0 ) =>
	source.startTags.filter( ( tag ) => tag.name === name )[ nth ];

const inner = ( source, name, nth = 0 ) => {
	const range = source.innerRange( tagNamed( source, name, nth ) );
	return range ? source.html.slice( range.start, range.end ) : null;
};

describe( 'SourceDocument', () => {
	describe( 'annotate', () => {
		it( 'numbers every start tag in source order', () => {
			const source = new SourceDocument(
				'<div class="a"><p>Hi <b>there</b></p><br></div>'
			);

			expect( source.annotate() ).toBe(
				'<div data-a8csp-ve-i="0" class="a"><p data-a8csp-ve-i="1">Hi <b data-a8csp-ve-i="2">there</b></p><br data-a8csp-ve-i="3"></div>'
			);
		} );

		it( 'renames inline event handlers and nothing else', () => {
			const source = new SourceDocument(
				'<a href="#" onclick="go()" ONLOAD=x data-on="1">x</a>'
			);

			expect( source.annotate() ).toBe(
				'<a data-a8csp-ve-i="0" href="#" data-a8csp-ve-onclick="go()" data-a8csp-ve-ONLOAD=x data-on="1">x</a>'
			);
		} );

		it( 'leaves the text of scripts, styles and comments alone', () => {
			const html =
				'<style>a > b {}</style><script>var a = "<p onclick=x>";</script><!-- <i> -->';
			const source = new SourceDocument( html );

			expect( source.annotate() ).toBe(
				'<style data-a8csp-ve-i="0">a > b {}</style><script data-a8csp-ve-i="1">var a = "<p onclick=x>";</script><!-- <i> -->'
			);
		} );
	} );

	it( 'reports a block that already uses the reserved prefix', () => {
		expect(
			new SourceDocument(
				'<p data-a8csp-ve-x="1">x</p>'
			).usesReservedPrefix()
		).toBe( true );
		expect( new SourceDocument( '<p>x</p>' ).usesReservedPrefix() ).toBe(
			false
		);
	} );

	describe( 'innerRange', () => {
		it( 'finds the content between a start tag and its end tag', () => {
			const source = new SourceDocument(
				'<div><p>One</p><p>Two <b>2</b></p></div>'
			);

			expect( inner( source, 'p', 1 ) ).toBe( 'Two <b>2</b>' );
			expect( inner( source, 'div' ) ).toBe(
				'<p>One</p><p>Two <b>2</b></p>'
			);
		} );

		it( 'counts nested elements of the same name', () => {
			const source = new SourceDocument(
				'<div>a<div>b<div>c</div></div>d</div><div>e</div>'
			);

			expect( inner( source, 'div', 0 ) ).toBe(
				'a<div>b<div>c</div></div>d'
			);
			expect( inner( source, 'div', 1 ) ).toBe( 'b<div>c</div>' );
			expect( inner( source, 'div', 3 ) ).toBe( 'e' );
		} );

		it( 'has no range for elements with no content of their own', () => {
			const source = new SourceDocument(
				'<p><img src="a"><br><svg><path d=""/><g></g></svg></p>'
			);

			expect( inner( source, 'img' ) ).toBeNull();
			expect( inner( source, 'br' ) ).toBeNull();
			expect( inner( source, 'path' ) ).toBeNull();
			expect( inner( source, 'g' ) ).toBe( '' );
		} );

		it( 'does not treat a self-closing HTML tag as closed', () => {
			const source = new SourceDocument( '<div/>text</div>' );

			expect( inner( source, 'div' ) ).toBe( 'text' );
		} );

		it( 'takes the whole text of a style or a script', () => {
			const source = new SourceDocument(
				'<style>a > b { color: red; }</style><script>var a = "</p>";</script>'
			);

			expect( inner( source, 'style' ) ).toBe( 'a > b { color: red; }' );
			expect( inner( source, 'script' ) ).toBe( 'var a = "</p>";' );
		} );

		it( 'has no range for an element that is never closed', () => {
			const source = new SourceDocument( '<ul><li>One<li>Two</ul>' );

			expect( inner( source, 'li' ) ).toBeNull();
		} );
	} );

	it( 'counts the start tags inside a range', () => {
		const source = new SourceDocument(
			'<div><p>a <b>b</b></p><i>c</i></div>'
		);
		const range = source.innerRange( tagNamed( source, 'div' ) );

		expect( source.countStartTags( range.start, range.end ) ).toBe( 3 );
	} );

	it( 'replaces a range and finds the tags again', () => {
		const source = new SourceDocument( '<div><p>Old</p><i>x</i></div>' );
		const range = source.innerRange( tagNamed( source, 'p' ) );

		source.replaceRange( range.start, range.end, 'New <b>text</b>' );

		expect( source.html ).toBe(
			'<div><p>New <b>text</b></p><i>x</i></div>'
		);
		expect( source.startTags.map( ( tag ) => tag.name ) ).toEqual( [
			'div',
			'p',
			'b',
			'i',
		] );
		expect( inner( source, 'i' ) ).toBe( 'x' );
	} );

	describe( 'setAttributes', () => {
		const change = ( html, name, changes ) => {
			const source = new SourceDocument( html );
			source.setAttributes( tagNamed( source, name ), changes );
			return source.html;
		};

		it( 'replaces the value of an attribute and keeps the rest as written', () => {
			expect(
				change(
					`<a  class='btn'\n   href="/old/" data-x=1>Go</a>`,
					'a',
					{ href: '/new/?a=1&b=2' }
				)
			).toBe(
				`<a  class='btn'\n   href="/new/?a=1&amp;b=2" data-x=1>Go</a>`
			);
		} );

		it( 'quotes a value that was unquoted or single-quoted', () => {
			expect(
				change( "<img src=a.png alt='x'>", 'img', {
					src: 'b c.png',
					alt: 'It\'s "x"',
				} )
			).toBe( '<img src="b c.png" alt="It\'s &quot;x&quot;">' );
		} );

		it( 'gives a value to an attribute that had none', () => {
			expect(
				change( '<img alt src="a">', 'img', { alt: 'A photo' } )
			).toBe( '<img alt="A photo" src="a">' );
		} );

		it( 'adds an attribute after the last one', () => {
			expect(
				change( '<a href="/x">Go</a>', 'a', { target: '_blank' } )
			).toBe( '<a href="/x" target="_blank">Go</a>' );
			expect(
				change( '<a href="/x" >Go</a>', 'a', { target: '_blank' } )
			).toBe( '<a href="/x" target="_blank" >Go</a>' );
			expect( change( '<br>', 'br', { class: 'x' } ) ).toBe(
				'<br class="x">'
			);
		} );

		it( 'adds an attribute ahead of a closing slash', () => {
			expect( change( '<img src="a" />', 'img', { alt: 'x' } ) ).toBe(
				'<img src="a" alt="x" />'
			);
			expect( change( '<img src="a"/>', 'img', { alt: 'x' } ) ).toBe(
				'<img src="a" alt="x"/>'
			);
		} );

		it( 'removes an attribute with the space before it', () => {
			expect(
				change(
					'<a href="/x"  target="_blank" rel="noopener">Go</a>',
					'a',
					{
						target: null,
						rel: null,
					}
				)
			).toBe( '<a href="/x">Go</a>' );
			expect(
				change( '<img srcset="a 1x" src="a">', 'img', { srcset: null } )
			).toBe( '<img src="a">' );
		} );

		it( 'ignores the removal of an attribute that is not there', () => {
			expect(
				change( '<a href="/x">Go</a>', 'a', { target: null } )
			).toBe( '<a href="/x">Go</a>' );
		} );

		it( 'applies several changes at once', () => {
			expect(
				change(
					'<img src="a.png" srcset="a 2x" width="10" height="20" alt>',
					'img',
					{
						src: 'b.png',
						srcset: null,
						height: '5',
						alt: 'B',
						loading: 'lazy',
					}
				)
			).toBe(
				'<img src="b.png" width="10" height="5" alt="B" loading="lazy">'
			);
		} );

		it( 'changes only the tag it was given', () => {
			const source = new SourceDocument(
				'<p><a href="/one">1</a> <a href="/two">2</a></p>'
			);
			source.setAttributes( tagNamed( source, 'a', 1 ), {
				href: '/three',
			} );

			expect( source.html ).toBe(
				'<p><a href="/one">1</a> <a href="/three">2</a></p>'
			);
			expect( inner( source, 'a', 1 ) ).toBe( '2' );
		} );
	} );

	it( 'escapes attribute values', () => {
		expect( escapeAttribute( 'a & b "c" <d>\u00a0' ) ).toBe(
			'a &amp; b &quot;c&quot; &lt;d&gt;&nbsp;'
		);
	} );
} );
