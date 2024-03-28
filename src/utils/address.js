export const addressToPrefixedAddress = ( addressObject, prefix ) => {
	return Object.keys( addressObject ).reduce( ( acc, key ) => {
		acc[ prefix + key ] = addressObject[ key ] || '';
		return acc;
	}, {} );
};
