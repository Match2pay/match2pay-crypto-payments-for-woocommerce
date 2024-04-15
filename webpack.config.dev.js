const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Overrides the default config to have deterministic file names
// and also RTL stylesheets.
module.exports = {
	...defaultConfig,
	optimization: {
		...defaultConfig.optimization,
		minimize: false,
	},
};
