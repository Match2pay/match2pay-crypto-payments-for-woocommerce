const $ = window.jQuery;
import React, { useEffect, useRef } from 'react';
import QRCodeLib from 'qrcode';

export const QrCode = ( { data } ) => {
	const myRef = useRef( null );

	useEffect( () => {
		if ( ! data ) return;

		const $canvas = $( myRef.current );
		if ( $canvas.data( 'encoded' ) !== data ) {
			$canvas.empty();
			new QRCodeLib( $canvas[ 0 ], {
				text: data,
				width: 256,
				height: 256,
				colorDark: '#000000',
				colorLight: '#ffffff',
				correctLevel: QRCodeLib.CorrectLevel.H,
			} );
			$canvas.data( 'encoded', data );
		}
	}, [ data ] );

	return <div id="match2pay-qr" ref={ myRef }></div>;
};
