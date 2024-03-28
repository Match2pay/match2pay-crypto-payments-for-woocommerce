const fs = require( 'fs' );
const path = require( 'path' );

const [ refName = 'localhost' ] = process.argv.slice( 2 );

const replacements = {
	__LAST_UPDATED__: new Date()
		.toISOString()
		.slice( 0, 19 )
		.replace( 'T', ' ' ),
};

const templateReplace = ( template, data ) => {
	let result = template;
	Object.keys( data ).forEach( ( key ) => {
		const value = data[ key ];
		result = result.replace( new RegExp( key, 'g' ), value );
	} );
	return result;
};

const build = async () => {
	const templatePath = path.resolve( __dirname, 'template.json' );
	const templateContent = fs.readFileSync( templatePath, 'utf8' );
	const result = templateReplace( templateContent, replacements );
	const buildPath = path.resolve(
		__dirname,
		'../updater',
		`${ refName }.json`
	);
	fs.writeFileSync( buildPath, result );
};

build();
