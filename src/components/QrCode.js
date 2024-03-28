const $ = window.jQuery;
import React, { useEffect, useRef } from 'react';

export const QrCode = ( { data } ) => {
	const myRef = useRef( null );

	if ( ! data ) {
		return <></>;
	}

	useEffect( () => {
		const $canvas = $( myRef.current );
		if ( $canvas.data( 'encoded' ) !== data ) {
			$canvas.empty();
			const qrcode = new QRCode( $canvas[ 0 ], {
				text: data,
				width: 256,
				height: 256,
				colorDark: '#000000',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.H,
			} );
			$canvas.data( 'encoded', data );
		}
	}, [ data ] );
	return <div id="match2pay-qr" ref={ myRef }></div>;
};
