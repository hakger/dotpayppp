<?php

/**
 * DotPay.php
 *
 * This is an implementation of a demonstration payment gateway plug-in.
 * It demonstrates how a payment plug-in can be developed, and contains stubs for all API functions.
 *
 * Description:
 *
 * The Payment SDK API requires a number functions to be implemented.
 * The function names must be named as follows: plug-in name prefix, underline symbol (e.g., DotPay_) and operation name (e.g., Sell)
 * Example :
 * 		function DotPay_Sell() {
 * 			...
 * 		}
 *
 *
 * Methods receive an array with some parameters specified for the *** function.
 * The array with parameters may contain some of these values:
 * 	ref_no  - Internal Billing transaction ID
 * 	transaction_amount - The amount to process within a transaction
 * 	currency_iso - The currency of the amount in the ISO format
 * 	account_info - Information on the Billing account including billing information, vendor information, etc.
 *	document_info - Information on the payment
 * 	payment_method - Information on the payment method including token, public name, etc.
 *	previous_transaction_data - Plug-in specific data that was received from the previous transaction
 *	callback_params - Parameters received from the payment gateway site on callback
 *	config - Plug-in configuration data
 * 	environment - Additional data that can be used for the payment processing: lang, callback URLs, etc.
 *
 * Methods return an array that mainly contains operation status.
 * The result array (if not specified) contains the following values:
 *	STATUS - Operation result status.
 * 			Possible values: APPROVED, FRAUD, DECLINED, PENDING, AUTHCALL, ERROR, REFUNDED, REDIRECT.
 * 	TRANSACTION_DETAILS - The array that will be associated with current transaction.
 * 			It will be represented back	in unchanged form as the value of the previous_transaction_data parameter
 * 			at the next call of transaction processing methods of the plug-in.
 * 	REDIRECT_HASH - Information for redirect. It must contain:
 * 			url - the URL where customer should be redirected to,
 * 			method - the method used to pass the data: GET, POST,
 * 			attrs - parameters and values which will be used for redirect.
 *	NEXT_TRANSACTION_GAP - The delay in seconds before another attempt to process the transaction will be taken
 * 			(for STATUS = PENDING)
 * 	TEXT - The array that contains parameters:
 * 			customer_message - message that will be shown for customer
 * 			vendor_message  - message that will be shown for vendor
 *	ADD_NEW_METHOD - array with payment method to create.
 * 			ADD_NEW_METHOD should contain paymethod_name value - it is a payment method's public name that is
 * 			displayed to the customers.	All other name-value pairs will be stored and passed to plug-in with
 * 			next payment operation within payment_method array.
 *
 * All methods can throw an exception if there is an error. Exception message will be shown in the provider panel.
 *
 */

require_once('DotPay_Helper.php');


/**
 * Returns parameters to configure plug-in.
 * Mandatory.
 *
 * @return array
 */

function DotPay_GetConfig( /*$params*/ ) {
	return array(
		'friendlyName' => pa_localized_string('title_key'),
		'enable_tokens' => array(
			'type' => 'yesno',
			'friendlyName' => pa_localized_string('enable_tokens_key'),
			'description' => pa_localized_string('enable_tokens_desc_key'),
			'default' => true
		),
		'3dsecure' => array(
			'type' => 'yesno',
			'friendlyName' => pa_localized_string('enable_3dsecure_key'),
			'description'  => pa_localized_string('enable_3dsecure_desc_key'),
			'default' => false
		)
	);
}


/**
 * Returns status APPROVED if 3D Secure enabled or DECLINE otherwise
 * Optional
 *
 * @return array
 */

function DotPay_Is3DSecureActive( $params ) {
	if ( $params['config']['3dsecure'] ) {
		return array( STATUS => STATUS_APPROVED );
	}
	return array( STATUS => STATUS_DECLINED );
}


/**
 * Implements redirection to the payment gateway page.
 * Mandatory if redirection to the gateway site is required
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- config
 *			- environment
 *
 * @return array with redirect attributes or error description (see description above)
 */

function DotPay_Redirect( $params ) {
	$config   = $params['config'];
	$doc_info = $params['document_info'];
	$ref_no   = $params['ref_no'];
	$env      = $params['environment'];

	$accept_url = $env['return_url_ok'];
	$decline_url = $env['return_url_failed'];

	$redirect_hash = array(
		'url' => 'https://demo-payment-gateway.com',
		'method' => 'POST',
		'attrs' => array(
			'ref_no' => $ref_no,
			'amount' => $params['transaction_amount'],
			'currency' => $params['currency_iso'],
			'accept_url' => $accept_url,
			'decline_url' => $decline_url,
			'enable_tokens' => isset($config['enable_tokens']) ? $config['enable_tokens'] : false
		)
	);

	return array(
		STATUS => STATUS_REDIRECT,
		REDIRECT_HASH => $redirect_hash
	);
}


/**
 * Implements Sell transaction with a payment gateway.
 * Mandatory for tokenization functionality implementation
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- config
 *			- environment
 * @return array with Sell transaction result (see description above)
 * @throws Exception
 */

function DotPay_Sell( $params ) {
	if (!isset($params['payment_method'])) {
		return DotPay_Redirect($params);
	}

	return array(
		STATUS => STATUS_APPROVED,
		TRANSACTION_DETAILS => array(
			'trans_id' => _DotPay_FakeTransactionId()
		)
	);
}


/**
 * Implements Auth transaction with a payment gateway.
 * Optional. Required for 3D Secure implementation
 *
 * @param $params - array of
 *                      - ref_no
 *                      - transaction_amount
 *                      - currency_iso
 *                      - account_info
 *                      - document_info
 *                      - payment_method
 *                      - previous_transaction_data
 *                      - config
 *                      - environment
 * @return array with Auth transaction result (see description above)
 * @throws Exception
 */

function DotPay_Auth( $params ) {
        if (!isset($params['payment_method'])) {
                return DotPay_Redirect($params);
        }

        if ( !$params['config']['3dsecure'] ) {
                return array(
                        STATUS => STATUS_APPROVED,
                        TRANSACTION_DETAILS => array(
                                'trans_id' => _DotPay_FakeTransactionId()
                        )
                );
        }

        if (isset($params['previous_transaction_data'])) {

                $result = array();
                if ($params['previous_transaction_data']['Enrolled'] === 'Y') {
                        $result[STATUS] = STATUS_APPROVED;
                } else {
                        $result[STATUS] = STATUS_DECLINED;
                        $result[TEXT] = array(
                                'vendor_message'   => 'Rejected by 3D Secure',
                                'customer_message' => 'Rejected by 3D Secure'
                        );
                }

                $result[TRANSACTION_DETAILS] = array(
                        'trans_id' => _DotPay_FakeTransactionId(),
                        'PaRes'    => $params['previous_transaction_data']['PaRes'],
                        'Enrolled' => $params['previous_transaction_data']['Enrolled']
                );

                return $result;
        }

        return array(
                STATUS => STATUS_3DSECURE,
                REDIRECT_HASH => array(
                        'url' => 'https://demo-payment-gateway.com/3dsecure.php',
                        'method' => 'POST',
                        'attrs' => array(
                                'PaReq'    => md5( (string) time() ),
                                'TermUrl'  => $params['environment']['return_url_3dsecure'],
                                'amount'   => $params['transaction_amount'],
                                'currency' => $params['currency_iso'],
                        )
                )
        );
}

/**
 * Implements Sell transaction with a payment gateway.
 * Mandatory if Auth method is implemented
 *
 * @param $params - array of
 *                      - ref_no
 *                      - transaction_amount
 *                      - currency_iso
 *                      - account_info
 *                      - document_info
 *                      - payment_method
 *                      - config
 *                      - environment
 * @return array with Capture transaction result (see description above)
 * @throws Exception
 */

function DotPay_Capture($params) {
        return array(
                STATUS => STATUS_APPROVED,
                TRANSACTION_DETAILS => array(
                        'trans_id' => _DotPay_FakeTransactionId()
                )
        );
}


/**
 * Implements RefundCredit transaction with a payment gateway.
 * Optional
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- config
 *			- environment
 *
 * @return array with RefundCredit transaction result  (see description above)
 * @throws Exception
 */

function DotPay_RefundCredit( $params ) {
	if (!isset($params['transaction_amount'])) {
		throw new Exception('No transaction amount is set');
	}

	if (!isset($params['currency_iso']) ) {
		throw new Exception('No currency is set');
	}

	return array(
		STATUS => STATUS_APPROVED,
		TRANSACTION_DETAILS => array(
			'trans_id' => _DotPay_FakeTransactionId()
		)
	);
}


/**
 * Processes 'Refund' transaction
 * Optional
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- previous_transaction_data
 * 			- config
 *			- environment
 *
 * @return array with Refund transaction result (see description above)
 * @throws Exception
 */

function DotPay_Refund( $params ) {
	if (!isset($params['previous_transaction_data'])) {
		throw new Exception('No previous transaction data');
	}

	return array(
		STATUS => STATUS_APPROVED,
		TRANSACTION_DETAILS => array(
			'trans_id' => _DotPay_FakeTransactionId()
		)
	);
}


/**
 * Implements Partial Refund transaction with a payment gateway.
 * Optional
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- previous_transaction_data
 * 			- config
 *			- environment
 *
 * @return array with Partial Refund transaction result (see description above)
 * @throws Exception
 */

function DotPay_RefundPartial( $params ) {
	if (!isset($params['previous_transaction_data'])) {
		throw new Exception('No previous transaction data');
	}

	return array(
		STATUS => STATUS_APPROVED,
		TRANSACTION_DETAILS => array(
			'trans_id' => _DotPay_FakeTransactionId()
		)
	);
}


/**
 * Implements voiding a transaction with a payment gateway.
 * Optional
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- previous_transaction_data
 * 			- config
 *			- environment
 *
 * @return array with Void transaction result (see description above)
 * @throws Exception
 */

function DotPay_Void( $params ) {
	if (!isset($params['previous_transaction_data'])) {
		throw new Exception('No previous transaction data');
	}

	return array(
		STATUS => STATUS_APPROVED,
		TRANSACTION_DETAILS => array(
			'trans_id' => _DotPay_FakeTransactionId()
		)
	);
}


/**
 * Processes callback data received from a payment gateway.
 * Optional
 *
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- previous_transaction_data
 * 			- callback_params
 * 			- config
 *			- environment
 *
 * @return array with Callback transaction result (see description above)
 */

function DotPay_Callback( $params ) {
	$callback_params = $params['callback_params'];

	$result = $callback_params['returnResult'];
	$txn_id = $callback_params['txn_id'];

	switch ($result) {
		case 'ok' :
			$status = STATUS_APPROVED;
			$desc   = 'Success';

			if (isset($callback_params['token']) && isset($callback_params['public_name'])) {
				$new_method = array(
					'token' => $callback_params['token'],
					'paymethod_name' => $callback_params['public_name']
					);
			}

			if (isset($new_method) && isset($callback_params['expires'])) {
				$new_method['exp_date'] = $callback_params['expires'];
			}

			break;

		case 'fail' :
			$status = STATUS_DECLINED;
			$desc   = 'Declined';
			break;

		default :
			$status = STATUS_ERROR;
			$desc   = "Unkown status '$result'";
			break;
	};

	$details['desc'] = $desc;
	$details['trans_id'] = $txn_id;

	$response = array(
		STATUS              => $status,
		TEXT                => array( 'vendor_message' => $desc ),
		TRANSACTION_DETAILS => $details
	);

	if (isset($new_method)) {
		$response[ADD_NEW_METHOD] = $new_method;
	}

	return $response;
}


/**
 * Checks a transaction current status on a payment gateway.
 * Optional
 *
 * @param $params - array of
 * 			- ref_no
 * 			- transaction_amount
 * 			- currency_iso
 * 			- account_info
 * 			- document_info
 * 			- payment_method
 * 			- previous_transaction_data
 * 			- config
 *			- environment
 *
 * @return array with CheckStatus transaction result (see description above)
 */

function DotPay_CheckStatus( /*$params*/ ) {
	return array(STATUS => STATUS_APPROVED);
}


/**
 * Tests gateway availability using plug-in's specified config.
 * Optional
 *
 * @param $params - array of
 * 			- config
 *			- environment
 *
 * @return array with status "APPROVED" if connection test is successful, or "DECLINED" otherwise
 */

function DotPay_TestConnection( $params ) {
	return array(STATUS => STATUS_APPROVED);
}


/**
 * Validates plug-in's stored configuration
 * Optional
 *
 * @param $params - array of
 * 			- config
 *			- environment
 *
 * @return array with status "APPROVED" if plug-in's configuration is valid, or "DECLINED" otherwise
 */

function DotPay_ValidateConfig( /* $params */ ) {
	return array(STATUS => STATUS_APPROVED);
}


/**
 * Returns a list of currencies supported by gateway.
 * Optional
 *
 * @return array of currencies
 */

function DotPay_GetSupportedCurrencies() {
	return array(
		'AED', # UAE Dirham
		'AFN', # Afghan afghani
		'ALL', # Lek
		'AMD', # Armenian Dram
		'ANG', # Antillian Guilder
		'ARS', # Argentine Peso
		'AUD', # Australian Dollar
		'AWG', # Aruban Guilder
		'BAM', # Convertible Marks
		'BBD', # Barbados Dollar
		'BDT', # Taka
		'BGN', # Bulgarian LEV
		'BHD', # Bahraini Dinar
		'BIF', # Burundi Franc
		'BMD', # Bermudian Dollar
		'BND', # Brunei Dollar
		'BRL', # Brazilian Real
		'BSD', # Bahamian Dollar
		'BTN', # Ngultrum
		'BWP', # Pula
		'BYR', # Belarussian Ruble
		'BZD', # Belize Dollar
		'CAD', # Canadian Dollar
		'CDF', # Franc Congolais
		'CHF', # Swiss Franc
		'CLP', # Chilean Peso
		'CNY', # Yuan Renminbi
		'COP', # Colombian Peso
		'CRC', # Costa Rican Colon
		'CUP', # Cuban Peso
		'CVE', # Cape Verde Escudo
		'CZK', # Czech Koruna
		'DJF', # Djibouti Franc
		'DKK', # Danish Krone
		'DOP', # Dominican Peso
		'DZD', # Algerian Dinar
		'EEK', # Kroon
		'EGP', # Egyptian Pound
		'ERN', # Nakfa
		'ETB', # Ethiopian Birr
		'EUR', # Euro
		'FJD', # Fiji Dollar
		'FKP', # Pound
		'GBP', # Pound Sterling
		'GEL', # Lari_r
		'GIP', # Gibraltar Pound
		'GMD', # Dalasi
		'GNF', # Guinea Franc
		'GTQ', # Quetzal
		'GYD', # Guyana Dollar
		'HKD', # Hong Kong Dollar
		'HNL', # Lempira
		'HRK', # Kuna
		'HTG', # Gourde
		'HUF', # Forint
		'IDR', # Rupiah
		'ILS', # New Israeli Sheqel
		'INR', # Indian Rupee
		'IQD', # Iraqi Dinar
		'IRR', # Iranian Rial
		'ISK', # Iceland Krona
		'JMD', # Jamaican Dollar
		'JOD', # Jordanian Dinar
		'JPY', # Yen
		'KES', # Kenyan Shilling
		'KGS', # Som
		'KHR', # Riel
		'KMF', # Comoro Franc
		'KPW', # North Korean Won
		'KRW', # Won
		'KWD', # Kuwaiti Dinar
		'KYD', # Cayman Islands Dollar
		'KZT', # Tenge
		'LAK', # Kip
		'LBP', # Lebanese Pound
		'LKR', # Sri Lanka Rupee
		'LRD', # Liberian Dollar
		'LSL', # Loti
		'LTL', # Lithuanian Litas
		'LVL', # Latvian Lats
		'LYD', # Libyan Dinar
		'MAD', # Moroccan Dirham
		'MDL', # Moldovan Leu
		'MKD', # Denar
		'MMK', # Kyat
		'MNT', # Tugrik
		'MOP', # Pataca
		'MUR', # Mauritius Rupee
		'MVR', # Rufiyaa
		'MWK', # Kwacha
		'MXN', # Mexican Peso
		'MYR', # Malaysian Ringgit
		'NAD', # Namibia Dollar
		'NGN', # Naira
		'NIO', # Cordoba Oro
		'NOK', # Norwegian Krone
		'NPR', # Nepalese Rupee
		'NZD', # New Zealand Dollar
		'OMR', # Rial Omani
		'PAB', # Balboa
		'PEN', # Nuevo Sol
		'PGK', # Kina
		'PHP', # Philippine Peso
		'PKR', # Pakistan Rupee
		'PLN', # Zloty
		'PYG', # Guarani
		'QAR', # Qatari Rial
		'RUB', # Russian Ruble
		'RWF', # Rwanda Franc
		'SAR', # Saudi Riyal
		'SBD', # Solomon Islands Dollar
		'SCR', # Seychelles Rupee
		'SEK', # Swedish Krona
		'SGD', # Singapore Dollar
		'SHP', # St Helena Pound
		'SLL', # Leone
		'SOS', # Somali Shilling
		'STD', # Dobra
		'SYP', # Syrian Pound
		'SZL', # Lilangeni
		'THB', # Baht
		'TJS', # Somoni
		'TND', # Tunisian Dinar
		'TOP', # Paanga
		'TRY', # Turkish Lira
		'TTD', # Trinidad and Tobago Dollar
		'TWD', # New Taiwan Dollar
		'TZS', # Tanzanian Shilling
		'UAH', # Hryvnia
		'UGX', # Uganda Shilling
		'USD', # US Dollar
		'UYU', # Peso Uruguayo
		'UZS', # Uzbekistan Sum
		'VND', # Dong
		'VUV', # Vatu
		'WST', # Tala
		'XAF', # CFA Franc BEAC
		'XCD', # East Caribbean Dollar
		'XOF', # CFA Franc BCEAO
		'XPF', # CFP Franc
		'YER', # Yemeni Rial
		'ZAR', # Rand
		'ZMK', # Kwacha
	);
}

?>
