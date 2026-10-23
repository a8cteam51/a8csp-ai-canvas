import {
	linkAttributes,
	pickSize,
	readMedia,
	sizeAttributes,
} from '../../assets/js/src/visual-editor/media';

const modalImage = {
	url: 'https://example.test/photo.jpg',
	width: 2000,
	height: 1000,
	alt: 'A photo',
	sizes: {
		thumbnail: {
			url: 'https://example.test/photo-150x150.jpg',
			width: 150,
			height: 150,
		},
		medium: {
			url: 'https://example.test/photo-300x150.jpg',
			width: 300,
			height: 150,
		},
		large: {
			url: 'https://example.test/photo-1024x512.jpg',
			width: 1024,
			height: 512,
		},
		full: {
			url: 'https://example.test/photo.jpg',
			width: 2000,
			height: 1000,
		},
	},
};

describe( 'readMedia', () => {
	it( 'reads an image as the media modal hands it over', () => {
		const image = readMedia( modalImage );

		expect( image.url ).toBe( 'https://example.test/photo.jpg' );
		expect( image.width ).toBe( 2000 );
		expect( image.alt ).toBe( 'A photo' );
		expect( image.sizes ).toHaveLength( 4 );
	} );

	it( 'reads an image as the REST API returns it', () => {
		const image = readMedia( {
			source_url: 'https://example.test/photo.jpg',
			alt_text: 'A photo',
			media_details: {
				width: 2000,
				height: 1000,
				sizes: {
					medium: {
						source_url: 'https://example.test/photo-300x150.jpg',
						width: 300,
						height: 150,
					},
				},
			},
		} );

		expect( image ).toEqual( {
			url: 'https://example.test/photo.jpg',
			width: 2000,
			height: 1000,
			alt: 'A photo',
			sizes: [
				{
					url: 'https://example.test/photo-300x150.jpg',
					width: 300,
					height: 150,
				},
			],
		} );
	} );

	it( 'reads an image with no sizes and no alternative text', () => {
		expect( readMedia( { url: 'https://example.test/a.svg' } ) ).toEqual( {
			url: 'https://example.test/a.svg',
			width: 0,
			height: 0,
			alt: '',
			sizes: [],
		} );
	} );
} );

describe( 'pickSize', () => {
	const image = readMedia( modalImage );

	it( 'takes the smallest file that fills the box at twice its size', () => {
		expect( pickSize( image, 140, 70 ).url ).toBe(
			'https://example.test/photo-300x150.jpg'
		);
		expect( pickSize( image, 400, 200 ).url ).toBe(
			'https://example.test/photo-1024x512.jpg'
		);
	} );

	it( 'takes the original when no smaller file is big enough', () => {
		expect( pickSize( image, 900, 300 ).url ).toBe(
			'https://example.test/photo.jpg'
		);
	} );

	it( 'leaves out the sizes that were cropped to another shape', () => {
		expect( pickSize( image, 60, 60 ).url ).toBe(
			'https://example.test/photo-300x150.jpg'
		);
	} );

	it( 'goes by the height of the box as well as its width', () => {
		expect( pickSize( image, 100, 200 ).url ).toBe(
			'https://example.test/photo-1024x512.jpg'
		);
	} );

	it( 'takes the original of an image whose size is not known', () => {
		expect(
			pickSize(
				readMedia( { url: 'https://example.test/a.svg' } ),
				10,
				10
			).url
		).toBe( 'https://example.test/a.svg' );
	} );
} );

describe( 'sizeAttributes', () => {
	it( 'keeps the width and fits the height to the new shape', () => {
		expect(
			sizeAttributes( '1024', '683', { width: 800, height: 1200 } )
		).toEqual( { height: '1536' } );
	} );

	it( 'sets nothing on an image that did not have both', () => {
		expect(
			sizeAttributes( '1024', null, { width: 800, height: 1200 } )
		).toEqual( {} );
		expect(
			sizeAttributes( null, null, { width: 800, height: 1200 } )
		).toEqual( {} );
	} );

	it( 'takes the file’s size where the width was not a number of pixels', () => {
		expect(
			sizeAttributes( '100%', '300', { width: 800, height: 1200 } )
		).toEqual( { width: '800', height: '1200' } );
	} );

	it( 'sets nothing for a file whose size is not known', () => {
		expect( sizeAttributes( '10', '10', { width: 0, height: 0 } ) ).toEqual(
			{}
		);
	} );
} );

describe( 'linkAttributes', () => {
	it( 'opens a link in the same tab by default', () => {
		expect( linkAttributes( '/x/', false ) ).toEqual( {
			href: '/x/',
			target: null,
			rel: null,
		} );
	} );

	it( 'adds what a link in a new tab needs', () => {
		expect( linkAttributes( '/x/', true ) ).toEqual( {
			href: '/x/',
			target: '_blank',
			rel: 'noopener',
		} );
	} );

	it( 'keeps the rest of rel either way', () => {
		expect( linkAttributes( '/x/', true, 'nofollow noopener' ).rel ).toBe(
			'nofollow noopener'
		);
		expect( linkAttributes( '/x/', false, 'nofollow noopener' ).rel ).toBe(
			'nofollow'
		);
		expect( linkAttributes( '/x/', false, 'NoOpener' ).rel ).toBeNull();
	} );
} );
