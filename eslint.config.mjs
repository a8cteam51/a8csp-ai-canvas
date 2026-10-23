import baseConfig from '@a8csp/configs/node/eslint.config.base.mjs';

export default [
	...baseConfig,
	{
		// Jest provides these to the unit tests; the shared test rules do not declare them.
		files: [ 'tests/JavaScript/**' ],
		languageOptions: {
			globals: {
				afterEach: 'readonly',
				beforeEach: 'readonly',
				describe: 'readonly',
				expect: 'readonly',
				it: 'readonly',
				jest: 'readonly',
			},
		},
	},
];
