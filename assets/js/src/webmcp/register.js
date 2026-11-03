/**
 * Registers the tools with the browser's WebMCP API as one set, so an agent sees all of them or
 * none. The tools send the agent to each other, and a partial set would send it to a tool that is
 * not there. Every registration takes the same abort signal, and when any of them fails, aborting
 * the signal unregisters the ones that succeeded.
 *
 * @param {Object}   modelContext The browser's WebMCP API.
 * @param {Object[]} tools        The tools.
 *
 * @return {Promise<boolean>} Whether the tools are registered.
 */
export async function registerTools( modelContext, tools ) {
	const controller = new AbortController();

	const results = await Promise.allSettled(
		tools.map( ( tool ) =>
			Promise.resolve().then( () =>
				modelContext.registerTool( tool, {
					signal: controller.signal,
				} )
			)
		)
	);

	const failures = results.filter(
		( result ) => 'rejected' === result.status
	);
	if ( ! failures.length ) {
		return true;
	}

	controller.abort();
	console.error(
		'AI Canvas: could not register every page tool, so none are registered.',
		...failures.map( ( failure ) => failure.reason )
	);

	return false;
}
