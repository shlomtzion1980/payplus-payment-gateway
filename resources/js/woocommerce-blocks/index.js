import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { getPaymentMethodData } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAMES } from './constants';

/**
 * Content component
 */
const Content = (prop) => {
	return decodeEntities( prop.description || '' );
};

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	const style ={width:'64px',height:'32px',maxHeight:'100%',margin:'0px 10px'}
	return <div className='payplus-method'  >

		<PaymentMethodLabel text={props.text}
							icon={props.icon ?<img style={style} src={props.icon}/>:''}/>
		<div className="pp_iframe"></div>
	</div>
};

/**
 * payplus payment method config object.
 */

const generatePayments = () =>{

	for (let i=0; i<PAYMENT_METHOD_NAMES.length;i++){
		const paymentMethod = PAYMENT_METHOD_NAMES[i];
		const settings = getPaymentMethodData(paymentMethod, {} );
		const defaultLabel = __(
			'Pay with Debit or Credit Card',
			'payplus-payment-gateway'
		);
		const label = decodeEntities( settings?.title || '' ) || defaultLabel;
		const Payment = {
			name: paymentMethod,
			label: <Label text={label} icon={settings.icon} />,
			content: <Content  description={settings.description} />,
			edit: <Content description={settings.description} />,
			canMakePayment: () => true,
			ariaLabel: label,
			supports: {
				features: settings.supports,
			},
		};
		registerPaymentMethod( Payment );
	}


}
generatePayments();
