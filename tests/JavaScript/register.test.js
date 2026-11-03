import { registerTools } from '../../assets/js/src/webmcp/register';

const tools = [ { name: 'one' }, { name: 'two' }, { name: 'three' } ];

/**
 * Stands in for the browser's WebMCP API, rejecting or throwing for the tools named.
 *
 * @param {Object}   failing
 * @param {string[]} failing.rejects Tools whose registration is rejected.
 * @param {string[]} failing.throws  Tools whose registration throws.
 *
 * @return {Object} The API.
 */
function modelContextFailing( { rejects = [], throws = [] } = {} ) {
	return {
		registerTool: jest.fn( ( tool ) => {
			if ( throws.includes( tool.name ) ) {
				throw new TypeError( `${ tool.name } is invalid` );
			}

			return rejects.includes( tool.name )
				? Promise.reject(
						new DOMException( 'Duplicate', 'InvalidStateError' )
					)
				: Promise.resolve();
		} ),
	};
}

const signalsOf = ( modelContext ) =>
	modelContext.registerTool.mock.calls.map(
		( [ , options ] ) => options.signal
	);

describe( 'registerTools', () => {
	it( 'registers every tool with one abort signal, and leaves it alone', async () => {
		const modelContext = modelContextFailing();

		await expect( registerTools( modelContext, tools ) ).resolves.toBe(
			true
		);

		const signals = signalsOf( modelContext );
		expect( signals ).toHaveLength( 3 );
		expect( new Set( signals ).size ).toBe( 1 );
		expect( signals[ 0 ].aborted ).toBe( false );
	} );

	it( 'unregisters the rest when one registration is rejected', async () => {
		const modelContext = modelContextFailing( { rejects: [ 'two' ] } );

		await expect( registerTools( modelContext, tools ) ).resolves.toBe(
			false
		);

		expect( modelContext.registerTool ).toHaveBeenCalledTimes( 3 );
		expect( signalsOf( modelContext )[ 0 ].aborted ).toBe( true );
		expect( console ).toHaveErrored();
	} );

	it( 'unregisters the rest when one registration throws', async () => {
		const modelContext = modelContextFailing( { throws: [ 'three' ] } );

		await expect( registerTools( modelContext, tools ) ).resolves.toBe(
			false
		);

		expect( signalsOf( modelContext )[ 0 ].aborted ).toBe( true );
		expect( console ).toHaveErrored();
	} );
} );
