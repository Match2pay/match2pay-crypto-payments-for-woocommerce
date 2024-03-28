import React, { useState, useEffect, useCallback } from 'react';
import Select from 'react-select';
import { addressToPrefixedAddress } from '../utils/address';
import { QrCode } from '../components/QrCode';
import { Button } from '../components/Button';
import { useInterval, useMount } from 'ahooks';

const { __ } = window.wp.i18n;
const { decodeEntities } = window.wp.htmlEntities;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

const settings = window.wc.wcSettings.getSetting( 'wc-match2pay_data', {} );

const currencies = Object.entries( settings.currencies ).map(
	( [ code, currency ] ) => {
		return {
			code,
			...currency,
		};
	}
);

const currencyOptions = currencies.map( ( currency ) => ( {
	value: currency.code,
	label: currency.code,
} ) );

const ajax_action = function ( url, _method, _data, sendJSON = true ) {
	return new Promise( ( resolve, reject ) => {
		const xmlhttp = new XMLHttpRequest();
		let dataToSend;

		if ( sendJSON ) {
			dataToSend = JSON.stringify( _data );
		} else {
			dataToSend = Object.keys( _data )
				.map(
					( key ) =>
						encodeURIComponent( key ) +
						'=' +
						encodeURIComponent( _data[ key ] )
				)
				.join( '&' );
		}

		xmlhttp.onreadystatechange = function () {
			if ( xmlhttp.readyState === 4 ) {
				if ( xmlhttp.status === 200 ) {
					try {
						const data = JSON.parse( xmlhttp.responseText );
						resolve( data );
					} catch ( err ) {
						console.warn(
							err.message + ' in ' + xmlhttp.responseText,
							err
						);
						reject( err );
					}
				} else {
					reject( xmlhttp.statusText );
				}
			}
		};

		xmlhttp.open( _method, url, true );

		if ( sendJSON ) {
			xmlhttp.setRequestHeader(
				'Content-Type',
				'application/json;charset=UTF-8'
			);
		} else {
			xmlhttp.setRequestHeader(
				'Content-Type',
				'application/x-www-form-urlencoded;charset=UTF-8'
			);
		}

		xmlhttp.send( dataToSend );
	} );
};

const getPaymentFormData = async ( data ) => {
	const url = decodeEntities(
		settings.endpoints.wc_match2pay_get_payment_form_data.url
	);

	if ( ! url ) {
		console.warn( 'missing ajax url for payment form data request' );
		return;
	}

	return await ajax_action( url, 'POST', data, false );
};

const CurrencySelect = ( { onChange, selectedOption } ) => {
	useEffect( () => {
		if ( currencyOptions.length === 1 ) {
			onChange( currencyOptions[ 0 ] );
		}
	} );

	if ( currencyOptions.length === 1 ) {
		return <></>;
	}

	return (
		<>
			<label>
				Select Payment Cryptocurrency{ ' ' }
				<span className="required">*</span>
			</label>
			<Select
				defaultValue={ selectedOption }
				options={ currencyOptions }
				onChange={ onChange }
			/>
		</>
	);
};

const Content = () => {
	return window.wp.htmlEntities.decodeEntities( 'Nothing to edit now.' );
};

const WatcherWidget = ( props ) => {
	const { paymentId, orderId } = props;
	const [ html, setHtml ] = useState( '' );
	const [ walletAddress, setWalletAddress ] = useState( '' );

	if ( ! paymentId || ! orderId ) {
		return <></>;
	}

	const loadWidgetHtml = async () => {
		try {
			const params = {
				match2pay_paymentId: paymentId,
				order_id: orderId,
			};
			const result = await ajax_action(
				decodeEntities( settings.endpoints.wc_match2pay_watcher.url ),
				'POST',
				params,
				false
			);

			if ( result.success === false ) {
				console.log(
					'error occured when requesting payment form data'
				);
			}

			const data = result.data;

			if ( data.result === 'success' && data.redirect ) {
				window.location.href = data.redirect;
				return;
			}

			const paymentStatus = data.paymentStatus;

			setWalletAddress( data.walletAddress );
			setHtml( data.fragment );

			if ( paymentStatus === 'COMPLETED' ) {
				// match2pay_submitForm();
			}
		} catch ( e ) {
			console.error( e );
			console.trace( e );
		}
	};

	useInterval( async () => {
		await loadWidgetHtml();
	}, settings.watcher_interval );

	useMount( async () => {
		if ( ! paymentId ) {
			return;
		}
		await loadWidgetHtml();
	} );

	return (
		<div className={ 'watcher-widget' }>
			{ walletAddress && <QrCode data={ walletAddress } /> }
			{ paymentId && (
				<div dangerouslySetInnerHTML={ { __html: html } }></div>
			) }
		</div>
	);
};

const Widget = ( props ) => {
	const [ loading, setLoading ] = useState( false );
	const [ selectedCurrency, setSelectedCurrency ] = useState( null );
	const [ paymentId, setPaymentId ] = useState( null );
	const [ order_id, setOrderId ] = useState( null );

	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;

	// setSelectedCurrency("ETH")
	// console.log(settings)
	const reInitialize = async () => {
		console.log( 'reInitialize', loading );
		if ( ! loading ) {
			if ( settings.paymentId ) {
				setPaymentId( settings.paymentId );
			}
			if ( settings.currency ) {
				setSelectedCurrency( settings.currency );
			}
			if ( settings.orderId ) {
				setOrderId( settings.orderId );
			}
		}
		setLoading( true );
	};

	// console.log('settings.paymentId',settings.paymentId)

	useEffect( () => {
		let unsubscribe;
		async function test() {
			if ( settings.paymentId && ! loading ) {
				await reInitialize();
			}
			const getFormData = () => {
				const currency = selectedCurrency.value;
				const formData = {
					order_id,
					currency,
					match2pay_currency: currency,
					...addressToPrefixedAddress(
						props.billing.billingAddress,
						'billing_'
					),
					...addressToPrefixedAddress(
						props.shippingData.shippingAddress,
						'shipping_'
					),
				};
				return formData;
			};

			unsubscribe = onPaymentSetup( async () => {
				//TODO: check if paymentId is set
				// if (!paymentId) {
				//     return {
				//         type: emitResponse.responseTypes.ERROR,
				//         message: 'Payment is required',
				//     };
				// }

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							...getFormData(),
						},
					},
				};

				//TODO: check status of payment

				// return {
				//     type: emitResponse.responseTypes.ERROR,
				//     message: 'There was an error',
				// };
			} );
		}

		test();
		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
		// paymentId,
		// selectedCurrency,
	] );

	const onCurrencySubmit = useCallback(
		async ( e ) => {
			e.preventDefault();
			const currency = selectedCurrency.value;

			const getFormData = () => {
				const formData = {
					order_id,
					currency,
					match2pay_currency: currency,
					...addressToPrefixedAddress(
						props.billing.billingAddress,
						'billing_'
					),
					...addressToPrefixedAddress(
						props.shippingData.shippingAddress,
						'shipping_'
					),
				};
				return formData;
			};
			const formData = getFormData();
			if ( ! formData.currency ) {
				console.error( 'currency is required' );
				return;
			}
			console.log( 'getFormData', formData );
			try {
				const result = await getPaymentFormData( formData );
				console.log( 'result', result );
				const { data, success } = result;
				if ( ! success ) {
					//TODO: show error message
					console.error(
						'error occured when requesting payment form data'
					);
					return;
				}
				console.log( 'result data', data );
				const { payment_form_data } = data;
				setOrderId( data.order_id );
				setPaymentId( payment_form_data.paymentId );
			} catch ( e ) {
				console.error( e );
				console.trace( e );
			}
			return false;
		},
		[ selectedCurrency ]
	);

	const description = window.wp.htmlEntities.decodeEntities(
		settings.description || ''
	);
	const buttonText = paymentId
		? __( 'Change Currency', 'wc-match2pay-crypto-payment' )
		: __( 'Pay with Cryptocurrency', 'wc-match2pay-crypto-payment' );

	const showChangeButton = true;

	return (
		<div className={ 'match2pay-payment-setting' }>
			{ description && <p>description</p> }
			{ showChangeButton && (
				<CurrencySelect
					selectedOption={ selectedCurrency }
					onChange={ setSelectedCurrency }
				/>
			) }
			{ showChangeButton && (
				<Button onClick={ onCurrencySubmit } text={ buttonText } />
			) }
			{ paymentId && (
				<WatcherWidget
					key={ paymentId }
					paymentId={ paymentId }
					orderId={ order_id }
				/>
			) }
		</div>
	);
};

const label =
	decodeEntities( settings.title ) || __( 'Match2Pay', 'wc-match2pay' );
const Block_Gateway = {
	name: 'match2pay',
	label,
	content: Object( window.wp.element.createElement )( Widget, null ),
	edit: Object( window.wp.element.createElement )( Content, null ),
	canMakePayment: ( ...args ) => {
		return true;
	},
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( Block_Gateway );
