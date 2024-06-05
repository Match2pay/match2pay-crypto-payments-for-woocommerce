const $ = window.jQuery;
import React, {useEffect, useMemo, useRef, useState} from 'react';
import QRCodeLib from 'qrcode';

export const QrCode = async ( props ) => {
	const { data } = props || {};
	const img = await useMemo(() => {
		if (!data) {
            return null;
        }
		return QRCodeLib.toDataURL(data, {
			errorCorrectionLevel: 'H',
			type: 'image/png',
			margin: 1,
			scale: 1,
			width: 256,
			height: 256,
			colorDark: '#000000',
			colorLight: '#ffffff',
		});
	}, [data]);

	return <div id="match2pay-qr">
		{ img && <img title={data} src={ img } alt="QR Code" /> }
	</div>;
};
