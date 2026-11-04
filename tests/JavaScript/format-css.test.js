import { formatCss } from '../../assets/js/src/webmcp/format-css';

// What a browser reads from a stylesheet, rule by rule, to compare two layouts of one stylesheet.
// jsdom keeps a selector list's white space as written, where a browser would not, so it goes.
const rulesOf = ( css ) => {
	const style = document.createElement( 'style' );
	style.textContent = css;
	document.head.append( style );
	const rules = Array.from( style.sheet.cssRules, ( rule ) =>
		rule.cssText.replace( /\s+/g, ' ' ).replace( / ?, ?/g, ',' )
	);
	style.remove();

	return rules;
};

const COMPACT = [
	'.canvas-x,.canvas-x *{box-sizing:border-box;}',
	'.canvas-x h1,.canvas-x h2{font-family:inherit;color:var(--ink);margin:0 0 .5em}',
	'.canvas-x .hero{background:#000 url("a;b{c}.png") center/cover no-repeat;padding:60px 0 0;}',
	'@media (min-width:900px){.canvas-x .hero{padding-top:80px;}.canvas-x .grid{grid-template-columns:1fr 1fr;}}',
].join( '' );

describe( 'formatCss', () => {
	it( 'puts each selector, declaration and closing brace on a line of its own', () => {
		expect( formatCss( COMPACT ) ).toBe(
			[
				'.canvas-x,',
				'.canvas-x * {',
				'  box-sizing: border-box;',
				'}',
				'',
				'.canvas-x h1,',
				'.canvas-x h2 {',
				'  font-family: inherit;',
				'  color: var(--ink);',
				'  margin: 0 0 .5em;',
				'}',
				'',
				'.canvas-x .hero {',
				'  background: #000 url("a;b{c}.png") center/cover no-repeat;',
				'  padding: 60px 0 0;',
				'}',
				'',
				'@media (min-width:900px) {',
				'  .canvas-x .hero {',
				'    padding-top: 80px;',
				'  }',
				'',
				'  .canvas-x .grid {',
				'    grid-template-columns: 1fr 1fr;',
				'  }',
				'}',
			].join( '\n' )
		);
	} );

	it( 'leaves the rules a browser reads as they were', () => {
		expect( rulesOf( formatCss( COMPACT ) ) ).toEqual( rulesOf( COMPACT ) );
	} );

	it( 'changes nothing the second time', () => {
		const once = formatCss( COMPACT );
		expect( formatCss( once ) ).toBe( once );
	} );

	it( 'keeps strings, unquoted URLs and parentheses as written', () => {
		const css =
			'.a::before{content:"{ ; }";background:url(data:image/png;base64,AAA=)}.a{width:calc(100% - (2 * 10px))}';
		expect( formatCss( css ) ).toBe(
			[
				'.a::before {',
				'  content: "{ ; }";',
				'  background: url(data:image/png;base64,AAA=);',
				'}',
				'',
				'.a {',
				'  width: calc(100% - (2 * 10px));',
				'}',
			].join( '\n' )
		);
	} );

	it( 'does not split a selector at a comma inside parentheses or brackets', () => {
		expect(
			formatCss( '.a :is(h1, h2),.a [data-x="1,2"]{color:red}' )
		).toBe( '.a :is(h1, h2),\n.a [data-x="1,2"] {\n  color: red;\n}' );
	} );

	it( 'puts a comment between rules on a line of its own, and keeps one inside a declaration', () => {
		expect( formatCss( '/* Hero */.a{color:red/* brand */;}' ) ).toBe(
			'/* Hero */\n.a {\n  color: red/* brand */;\n}'
		);
	} );

	it( 'keeps an escaped character in a selector', () => {
		expect( formatCss( '.sm\\:p-4{padding:1rem}' ) ).toBe(
			'.sm\\:p-4 {\n  padding: 1rem;\n}'
		);
	} );

	it( 'keeps braces inside a custom property’s value', () => {
		expect( formatCss( '.a{--x:{a:b};color:red}' ) ).toBe(
			'.a {\n  --x: {a:b};\n  color: red;\n}'
		);
	} );

	it( 'keeps an at-rule without a block, and !important', () => {
		expect(
			formatCss( '@import url(a.css);.a{color:red!important}' )
		).toBe( '@import url(a.css);\n.a {\n  color: red!important;\n}' );
	} );

	it( 'lays out keyframes and font faces', () => {
		expect(
			formatCss( '@keyframes glow{0%{opacity:0}to{opacity:1}}' )
		).toBe(
			[
				'@keyframes glow {',
				'  0% {',
				'    opacity: 0;',
				'  }',
				'',
				'  to {',
				'    opacity: 1;',
				'  }',
				'}',
			].join( '\n' )
		);
	} );

	it( 'returns an empty stylesheet empty', () => {
		expect( formatCss( '  \n ' ) ).toBe( '' );
	} );
} );
