import {
	levelsChangingLook,
	selectorsOf,
	swapTag,
} from '../../assets/js/src/visual-editor/heading-level';

const rootOf = ( html ) => {
	document.body.innerHTML = `<div id="root">${ html }</div>`;
	return document.getElementById( 'root' );
};

const levelsFor = ( html, selectors ) => {
	const root = rootOf( html );
	return levelsChangingLook(
		root,
		root.querySelector( 'h1, h2, h3, h4, h5, h6' ),
		selectors
	);
};

describe( 'levelsChangingLook', () => {
	const HERO =
		'<div class="page"><section class="hero"><h1>The city sleeps. <span>We run.</span></h1></section></div>';

	it( 'finds every other level when a rule styles the heading by its level', () => {
		expect( levelsFor( HERO, [ '.page .hero h1' ] ) ).toEqual( [
			2, 3, 4, 5, 6,
		] );
	} );

	it( 'finds every other level when a rule styles what is in the heading by its level', () => {
		expect( levelsFor( HERO, [ '.page .hero h1 span' ] ) ).toEqual( [
			2, 3, 4, 5, 6,
		] );
	} );

	it( 'passes over the levels a rule for several levels covers', () => {
		expect( levelsFor( HERO, [ '.page h1, .page h2, .page h3' ] ) ).toEqual(
			[ 4, 5, 6 ]
		);
	} );

	it( 'finds nothing when the heading is styled by a class', () => {
		const html =
			'<div class="page"><h2 class="title">Title <span class="accent">here</span></h2></div>';
		expect(
			levelsFor( html, [ '.page .title', '.page .title .accent' ] )
		).toEqual( [] );
	} );

	it( 'finds the levels a rule for another level would bring in', () => {
		const html = '<div class="page"><h2>Title</h2></div>';
		expect( levelsFor( html, [ '.page h4' ] ) ).toEqual( [ 4 ] );
	} );

	it( 'leaves the page as it was', () => {
		const root = rootOf( HERO );
		const before = root.innerHTML;
		levelsChangingLook( root, root.querySelector( 'h1' ), [
			'.page .hero h1',
		] );
		expect( root.innerHTML ).toBe( before );
	} );

	it( 'skips a selector the browser cannot read', () => {
		expect( levelsFor( HERO, [ ':nonsense(', '.page h1' ] ) ).toEqual( [
			2, 3, 4, 5, 6,
		] );
	} );
} );

describe( 'selectorsOf', () => {
	it( 'lists the selectors inside @media, without pseudo-elements', () => {
		const style = document.createElement( 'style' );
		style.textContent =
			'.page h1 { color: red } @media (min-width: 600px) { .page h1::after { content: "" } }';
		document.head.append( style );

		expect( selectorsOf( style.sheet ) ).toEqual( [
			'.page h1',
			'.page h1',
		] );
		style.remove();
	} );
} );

describe( 'swapTag', () => {
	it( 'keeps the attributes, including one setAttribute() refuses, and the content', () => {
		const root = rootOf(
			'<h4 class="x" @click="go()">Hi <em>there</em></h4>'
		);
		const renamed = swapTag( root.firstElementChild, 'h6' );

		expect( root.innerHTML ).toBe(
			'<h6 class="x" @click="go()">Hi <em>there</em></h6>'
		);
		expect( renamed.localName ).toBe( 'h6' );
	} );
} );
