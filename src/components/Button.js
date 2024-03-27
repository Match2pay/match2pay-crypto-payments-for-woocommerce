import React from 'react';

export const Button = ( props ) => {
	const { onClick, text } = props;
	return (
		<button
			className={ 'button alt match2pay-pay-with' }
			onClick={ onClick }
		>
			{ text }
		</button>
	);
};
