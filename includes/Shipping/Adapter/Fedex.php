<?php

/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         3-7170 Ash Cres                                 */
/*  OF               Vancouver BC   V6P 3K7                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

namespace OneTeamSoftware\WooCommerce\Shipping\Adapter;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\Fedex')):

class Fedex extends AbstractAdapter
{
	protected $soapRequestBuilder;
	protected $soapResponseParser;

	protected $displayDeliveryTime;
	protected $accounts;
	protected $key;
	protected $password;

	// we don't want these properties overwritten by settings
	protected $_fedexOneRatePackageTypes;
	protected $_freightPackageTypes;
	protected $_defaultPackageType;

	const MIN_WEIGHT = 0.2;
	const MAX_DESCRIPTION_LENGTH = 45;

	public function __construct($id, array $settings = array())
	{
		$this->displayDeliveryTime = false;
		$this->accounts = array();
		$this->key = null;
		$this->password = null;
		$this->origin = array();
		
		parent::__construct($id, $settings);

		$this->soapRequestBuilder = new \OneTeamSoftware\WooCommerce\Xml\SoapRequestBuilder();
		$this->soapResponseParser = new \OneTeamSoftware\WooCommerce\Xml\SoapResponseParser();

		$this->currencies = array('USD' => __('USD', $this->id), 'CAD' => __('CAD', $this->id));

		$this->contentTypes = array(
			'SOLD' => __('Merchandise', $this->id),
			'NOT_SOLD' => __('Not Sold', $this->id),
			'GIFT' => __('Gift', $this->id),
			'PERSONAL_EFFECTS' => __('Personal Effects', $this->id),
			'REPAIR_AND_RETURN' => __('Returned Goods', $this->id),
			'SAMPLE' => __('Sample', $this->id),
		);

		$this->initServices();
		$this->initPackageTypes();
	}

	public function getName()
	{
		return 'FedEx';
	}

	public function hasCustomItemsFeature()
	{
		return true;
	}

	public function hasUseSellerAddressFeature()
	{
		return true;
	}

	public function hasReturnLabelFeature()
	{
		return true;
	}

	public function hasAddressValidationFeature()
	{
		return true;
	}

	public function hasLinkFeature()
	{
		return true;
	}

	public function hasMediaMailFeature()
	{
		return true;
	}

	public function hasOriginFeature()
	{
		return true;
	}

	public function hasInsuranceFeature()
	{
		return true;
	}

	public function hasSignatureFeature()
	{
		return true;
	}

	public function hasAlcoholFeature()
	{
		return true;
	}

	public function hasDryIceFeature()
	{
		return true;
	}

	public function hasCodFeature()
	{
		return true;
	}

	public function hasDisplayDeliveryTimeFeature()
	{
		return true;
	}

	public function hasUpdateShipmentsFeature()
	{
		return true;
	}

	public function hasFreightClassFeature()
	{
		return true;
	}

	public function validate(array $settings)
	{
		$errors = array();

		if (empty($settings['origin'])) {
			$settings['origin'] = array();
		}

		$this->setSettings($settings);
		$this->cache = false;
		
		foreach ($this->accounts as $accountType => $account) {
			$response = $this->validateAddressForAccount($accountType, $account, $settings['origin']);
			if (!empty($response['error']['message'])) {
				$errors[] = sprintf('<strong>%s:</strong> %s', ucwords($accountType), $response['error']['message']);

				break;

			} else if (!empty($response['addressValidation']['errors']) && is_array($response['addressValidation']['errors'])) {
				foreach ($response['addressValidation']['errors'] as $errorMessage) {
					$errors[] = sprintf('<strong>%s</strong> %s', __('From Address', $this->id), $errorMessage);
				}

				break;
			}
		}	

		return $errors;
	}

	public function getIntegrationFormFields()
	{
		$countryState = explode(':', get_option('woocommerce_default_country', ''));

		$defaultCountry = '';
		if (count($countryState) > 0) {
			$defaultCountry = $countryState[0];
		}

		$defaultState = '';
		if (count($countryState) > 1) {
			$defaultState = $countryState[1];
		}

		$states = null;
		if (isset($this->accounts['freight']['address']['country'])) {
			$states = WC()->countries->get_states($this->accounts['freight']['address']['country']);
		}

		$formFields = array(
			'key' => array(
				'title' => __('Key', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_REGEXP,
				'filter_options' => array('options' => array('regexp' => '/[a-z0-9]+$/i')),
				'optional' => true,
				'description' => '', //TODO: add info
			),
			'password' => array(
				'title' => __('Password', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_REGEXP,
				'filter_options' => array('options' => array('regexp' => '/[a-z0-9]+$/i')),
				'optional' => true,
				'description' => '', //TODO: add info
			),

			'fedexExpressAccountInfo' => array(
				'title' => __('Express Account Information', $this->id),
				'type' => 'title'
			),
			'accounts[express][enabled]' => array(
				'title' => __('Enable / Disable', $this->id),
				'type' => 'checkbox',
				'label' => __('Enable this service', $this->id),
			),
			'accounts[express][clientDetail][AccountNumber]' => array(
				'title' => __('Account Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[express][clientDetail][MeterNumber]' => array(
				'title' => __('Meter Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),

			'fedexSmartPostAccountInfo' => array(
				'title' => __('SmartPost Account Information', $this->id),
				'type' => 'title'
			),
			'accounts[smartpost][enabled]' => array(
				'title' => __('Enable / Disable', $this->id),
				'type' => 'checkbox',
				'label' => __('Enable this service', $this->id),
			),
			'accounts[smartpost][clientDetail][AccountNumber]' => array(
				'title' => __('Account Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[smartpost][clientDetail][MeterNumber]' => array(
				'title' => __('Meter Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[smartpost][HubId]' => array(
				'title' => __('Hub ID', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),

			'fedexFreightAccountInfo' => array(
				'title' => __('Freight Account Information', $this->id),
				'type' => 'title'
			),
			'accounts[freight][enabled]' => array(
				'title' => __('Enable / Disable', $this->id),
				'type' => 'checkbox',
				'label' => __('Enable this service', $this->id),
			),
			'accounts[freight][clientDetail][AccountNumber]' => array(
				'title' => __('Account Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[freight][clientDetail][MeterNumber]' => array(
				'title' => __('Meter Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[freight][FedExFreightAccountNumber]' => array(
				'title' => __('Freight Account Number', $this->id),
				'type' => 'text',
				'filter' => FILTER_VALIDATE_INT,
				'optional' => true,
			),
			'accounts[freight][defaultFreightPackageType]' => array(
				'title' => __('Default Freight Package Type', $this->id),
				'type' => 'select',
				'options' => $this->_freightPackageTypes,
				'default' => 'PALLET',
			),
			'fedexFreightAccountBillingAddress' => array(
				'title' => __('Freight Account Billing Address', $this->id),
				'type' => 'title',
				'description' => __('This address should exactly match the address FedEx has on file for your freight account.', $this->id)
			),
			'accounts[freight][address][sameAsFromAddress]' => array(
				'title' => __('Same As From Address', $this->id),
				'label' => __('If checked then From Address will be used instead', $this->id),
				'type' => 'checkbox',
				'default' => 'no',
			),
			'accounts[freight][address][name]' => array(
				'title' => __('Name', $this->id),
				'type' => 'text',
			),
			'accounts[freight][address][company]' => array(
				'title' => __('Company', $this->id),
				'type' => 'text',
			),
			'accounts[freight][address][email]' => array(
				'title' => __('Email', $this->id),
				'type' => 'email',
				'default' => get_option('admin_email')
			),
			'accounts[freight][address][phone]' => array(
				'title' => __('Phone', $this->id),
				'type' => 'text',
			),
			'accounts[freight][address][country]' => array(
				'title' => __('Country', $this->id),
				'type' => 'select',
				'default' => $defaultCountry,
				'options' => WC()->countries->get_countries(),
				'custom_attributes' => array('onchange' => 'jQuery("[name=save]").click()'),
			),
			'accounts[freight][address][state]' => array(
				'title' => __('State', $this->id),
				'type' => empty($states) ? 'text' : 'select',
				'default' => $defaultState,
				'options' => $states,
			),
			'accounts[freight][address][city]' => array(
				'title' => __('City', $this->id),
				'type' => 'text',
				'default' => get_option('woocommerce_store_city', '')
			),
			'accounts[freight][address][postcode]' => array(
				'title' => __('Zip / Postal Code', $this->id),
				'type' => 'text',
				'default' => get_option('woocommerce_store_postcode', '')
			),
			'accounts[freight][address][address]' => array(
				'title' => __('Address 1', $this->id),
				'type' => 'text',
				'default' => get_option('woocommerce_store_address', '')
			),
			'accounts[freight][address][address_2]' => array(
				'title' => __('Address 2', $this->id),
				'type' => 'text',
				'default' => get_option('woocommerce_store_address_2', '')
			),
		);

		return $formFields;
	}

	public function getRates(array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRates');

		$params['function'] = __FUNCTION__;
		$response = array();

		$errorMessage = '';

		foreach ($this->accounts as $accountType => $account) {
			$newResponse = $this->getRatesForAccount($accountType, $account, $params);
			if (empty($newResponse)) {
				continue;
			}

			$response['response'][$accountType] = $newResponse['response'];
			$response['params'][$accountType] = $newResponse['params'];

			if (!empty($newResponse['validation_errors'])) {
				$response['validation_errors'] = $newResponse['validation_errors'];
			}

			if (!empty($newResponse['shipment'])) {
				if (empty($response['shipment'])) {
					$response['shipment'] = $newResponse['shipment'];
				} else {
					$response['shipment'] = array_replace_recursive($response['shipment'], $newResponse['shipment']);
				}
			} else if (!empty($newResponse['error']['message'])) {
				if (!empty($errorMessage)) {
					$errorMessage .= "\n";
				}

				$errorMessage .= $accountType . ': ' . $newResponse['error']['message'];
			}
		}

		if (!empty($response['shipment']['rates'])) {
			$response['shipment']['rates'] = $this->sortRates($response['shipment']['rates']);
		}

		if (!empty($errorMessage)) {
			$response['error']['message'] = $errorMessage;

			$this->logger->debug(__FILE__, __LINE__, 'Errors: ' . $errorMessage);
		}

		$this->logger->debug(__FILE__, __LINE__, 'Response: ' . print_r($response, true));
		
		return $response;
	}

	protected function getRatesForAccount($accountType, array $account, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRatesForAccount: ' . $accountType . ' | ' . print_r($account, true) . '|' . print_r($params, true));

		if (empty($account['enabled'])) {
			$this->logger->debug(__FILE__, __LINE__, 'Account is disabled: ' . print_r($account, true));

			return array();
		}

		if (!empty($params['type']) && isset($this->_fedexOneRatePackageTypes[$params['type']])) {
			if ($accountType != 'express') {
				$this->logger->debug(__FILE__, __LINE__, 'Package is only suitable for express, so skip the account');

				return array();
			}
		}

		if (!empty($params['type']) && isset($this->_freightPackageTypes[$params['type']])) {
			if ($accountType != 'freight') {
				$this->logger->debug(__FILE__, __LINE__, 'Package is only suitable for freight, so skip the account');

				return array();
			}
		}

		if ($accountType == 'freight' && empty($this->_freightPackageTypes[$params['type']]) && $params['type'] != $this->_defaultPackageType) {
			$this->logger->debug(__FILE__, __LINE__, 'Package is not suitable for freight, so skip the account: ' . $params['type'] . ' ? ' . $this->_defaultPackageType);

			return array();
		}

		$mediaMail = $this->getRequestedMediaMail($params);
		if (!empty($mediaMail) &&  $mediaMail != 'exclude' && $accountType != 'smartpost') {
			$this->logger->debug(__FILE__, __LINE__, 'Media Mail package is only suitable for SmartPost, so skip the account: ' . $params['type']);

			return array();
		}

		$requestParams = array_replace_recursive($account, $params);
		$requestParams['accountType'] = $accountType;

		$cacheKey = $this->getRatesCacheKey($requestParams);
		$newResponse = $this->getCacheValue($cacheKey);
		if (!empty($newResponse)) {
			$this->logger->debug(__FILE__, __LINE__, 'Found previously returned rates');
		} else {
			$newResponse = $this->sendSoapRequest($requestParams);

			if (!empty($newResponse['shipment'])) {
				$this->logger->debug(__FILE__, __LINE__, 'Cache shipment for the future');
			
				$this->setCacheValue($cacheKey, $newResponse, $this->cacheExpirationInSecs);
			}		
		}

		return $newResponse;
	}

	public function validateAddress(array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'validateAddress');

		$response = array();

		foreach ($this->accounts as $accountType => $account) {
			$response = $this->validateAddressForAccount($accountType, $account, $params);
			if (!empty($response)) {
				break;
			}
		}

		$this->logger->debug(__FILE__, __LINE__, 'Address Validation Result: ' . print_r($response, true));
		
		return $response;
	}

	protected function validateAddressForAccount($accountType, array $account, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'validateAddressForAccount: ' . $accountType . ' | ' . print_r($account, true) . '|' . print_r($params, true));

		if (empty($account['enabled'])) {
			$this->logger->debug(__FILE__, __LINE__, 'Account is disabled: ' . print_r($account, true));

			return array();
		}

		$requestParams = array_replace_recursive($account, $params);
		$requestParams['accountType'] = $accountType;
		$requestParams['function'] = __FUNCTION__;

		$cacheKey = $this->getCacheKey($requestParams);
		$response = $this->getCacheValue($cacheKey);
		if (empty($response)) {
			$response = $this->sendSoapRequest($requestParams);

			if (empty($response['error']['message'])) {
				$this->setCacheValue($cacheKey, $response);
			}
		} else {
			$this->logger->debug(__FILE__, __LINE__, 'Found previously validated address');
		}

		return $response;
	}

	protected function getRatesCacheKey(array $params)
	{
		$params['validateAddress'] = $this->validateAddress;

		if (isset($params['service'])) {
			unset($params['service']);
		}

		if (isset($params['function'])) {
			unset($params['function']);
		}

		return $this->getCacheKey($params) . '_rates';
	}

	protected function getRatesParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRatesParams');

		if (empty($inParams['origin'])) {
			$inParams['origin'] = $this->origin;
		}

		$params = array();

		// order of definition is critical
		$params['WebAuthenticationDetail']['UserCredential'] = $this->getUserCredential();
		$params['ClientDetail'] = $this->getClientDetail($inParams);

		// version is required for API to work
		$params['Version'] = $this->getVersion('crs', 16);

		if ($this->displayDeliveryTime) {
			$params['ReturnTransitAndCommit'] = true;
		}

		$params['RequestedShipment'] = $this->getRequestedShipment($inParams);
		
		return array('http://fedex.com/ws/rate/v16', array('RateRequest' => $params));
	}

	protected function getValidateAddressParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getValidateAddressParams');

		$params = array();

		// order of definition is critical
		$params['WebAuthenticationDetail']['UserCredential'] = $this->getUserCredential();
		$params['ClientDetail'] = $this->getClientDetail($inParams);

		// version is required for API to work
		$params['Version']['ServiceId'] = 'aval';
		$params['Version']['Major'] = 4;
		$params['Version']['Intermediate'] = 0;
		$params['Version']['Minor'] = 0;

		$params['InEffectAsOfTimestamp'] = date('c');

		$params['AddressesToValidate'] = $this->getAddress($inParams);

		return array('http://fedex.com/ws/addressvalidation/v4', array('AddressValidationRequest' => $params));
	}

	protected function getRequestParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRequestParams: ' . print_r($inParams, true));

		$function = '';
		if (!empty($inParams['function'])) {
			$function = $inParams['function'];
		}

		$params = array();

		if ($function == 'getRates') {
			$params = $this->getRatesParams($inParams);
		} else if ($function == 'validateAddressForAccount') {
			$params = $this->getValidateAddressParams($inParams);
		}

		return $params;
	}

	protected function parseRate($rateReply, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'parseRate: ' . print_r($rateReply, true));

		if (empty($rateReply['ServiceType'])) {
			$this->logger->debug(__FILE__, __LINE__, 'ServiceType has not been found');
			
			return null;
		}

		$shipmentRateDetail = null;
		if (isset($rateReply['RatedShipmentDetails'][0]['ShipmentRateDetail'])) {
			$shipmentRateDetail = &$rateReply['RatedShipmentDetails'][0]['ShipmentRateDetail'];
		} else if (isset($rateReply['RatedShipmentDetails']['ShipmentRateDetail'])) {
			$shipmentRateDetail = &$rateReply['RatedShipmentDetails']['ShipmentRateDetail'];
		} else {
			$this->logger->debug(__FILE__, __LINE__, 'ShipmentRateDetail has not been found');

			return null;
		}

		$serviceTypeAndName = $this->parseServiceTypeAndName($rateReply);
		if (empty($serviceTypeAndName)) {
			$this->logger->debug(__FILE__, __LINE__, 'Unable to parse neither Service Type or Name');

			return null;
		}

		$rate = array();
		$rate['service'] = $serviceTypeAndName['serviceType'];
		$rate['postage_description'] = apply_filters($this->id . '_service_name', $serviceTypeAndName['serviceName'], $serviceTypeAndName['serviceType']);

		$cost = floatval($shipmentRateDetail['TotalNetCharge']['Amount']);
		$rate['cost'] = $cost;
		$rate['insurance_fee'] = 0;
		$rate['delivery_fee'] = 0;
		$rate['tracking_type_description'] = '';
		$rate['delivery_time_description'] = '';

		if (isset($rateReply['CommitDetails']['CommitTimestamp'])) {
			$deliveryDays = $this->getDaysDiff('now', $rateReply['CommitDetails']['CommitTimestamp']);
			if ($deliveryDays > 0) {
				$rate['delivery_days'] = $deliveryDays;
				$rate['delivery_time_description'] = sprintf(__('Estimated delivery in %d days', $this->id), $deliveryDays);
			}
		}

		$this->logger->debug(__FILE__, __LINE__, 'rate: ' . print_r($rate, true));
		
		return $rate;
	}
	
	protected function parseServiceTypeAndName(array $rateReply)
	{
		$serviceTypeAndName = array();

		$serviceType = null;
		if (!empty($rateReply['ServiceType'])) {
			$serviceType = $rateReply['ServiceType'];
		} else if (!empty($rateReply['ServiceDescription']['ServiceType'])) {
			$serviceType = $rateReply['ServiceDescription']['ServiceType'];
		} else if (!empty($rateReply['ServiceTypeDescription'])) {
			$serviceType = strtoupper(str_replace(' ', '_', $rateReply['ServiceTypeDescription']));
		}

		if (!empty($serviceType)) {
			$serviceTypeAndName['serviceType'] = $serviceType;

			$serviceName = '';
			if (!empty($rateReply['ServiceDescription']['Names'][0]['Value'])) {
				$serviceName = utf8_decode($rateReply['ServiceDescription']['Names'][0]['Value']);
			} else if (!empty($serviceType) && !empty($this->_services[$serviceType])) {
				$serviceName = $this->_services[$serviceType];
			}

			$serviceTypeAndName['serviceName'] = $serviceName;
		}

		return $serviceTypeAndName;
	}

	protected function getRatesResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRatesResponse');
		if (empty($response['RateReply'])) {
			return array();
		}

		$newResponse = array();
		$errorMessage = $this->getErrorMessage($response['RateReply']);
		if (!empty($errorMessage)) {
			$newResponse['error']['message'] = $errorMessage;
		}

		if (empty($response['RateReply']['RateReplyDetails'])) {
			return $newResponse;
		}

		if ($this->validateAddress) {
			$validateAddressResponse = $this->validateAddress($params['destination']);
			if (!empty($validateAddressResponse['addressValidation']['errors'])) {
				$newResponse['validation_errors']['destination'] = $validateAddressResponse['addressValidation']['errors'];
			}
		}

		$rates = array();

		$rateReplies = $response['RateReply']['RateReplyDetails'];
		if (!isset($rateReplies[0])) {
			$rateReplies = array($rateReplies);
		}

		foreach ($rateReplies as $rateReply) {
			$rate = $this->parseRate($rateReply, $params);
			if (!empty($rate)) {
				$rates[$rate['service']] = $rate;
			}
		}

		$shipment = array();
		$shipment['ship_date'] = date('Y-m-d H:i:s');
		$shipment['rates'] = $rates;

		$newResponse['shipment'] = $shipment;

		return $newResponse;
	}

	protected function getValidateAddressResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getValidateAddressResponse');

		if (empty($response['AddressValidationReply'])) {
			$newResponse['error']['message'] = __('Unrecognized response', $this->id);

			return $newResponse;
		}

		$addressValidation = array();

		if (isset($response['AddressValidationReply']['AddressResults'])) {
			$addressResults = &$response['AddressValidationReply']['AddressResults'];

			if (!empty($addressResults['Classification'])) {
				if ($addressResults['Classification'] == 'RESIDENTIAL') {
					$addressValidation['residential'] = true;
				} else if ($addressResults['Classification'] == 'BUSINESS') {
					$addressValidation['residential'] = false;
				}
			}

			$addressValidation['address'] = $this->parseEffectiveAddress($addressResults);

			$attributes = $this->parseAddressAttributes($addressResults);

			if (!empty($attributes['CountrySupported'])) {
				$addressValidation['countrySupported'] = true;
			} else {
				$addressValidation['countrySupported'] = false;
				$this->logger->debug(__FILE__, __LINE__, 'Country is not supported');
			}

			if (!empty($attributes['InvalidSuiteNumber'])) {
				$addressValidation['errors'][] = __('Suite information was provided and was either incorrect, or was provided for an address that was not recognized as requiring secondary information', $this->id);
			}

			if (!empty($attributes['SuiteRequiredButMissing'])) {
				$addressValidation['errors'][] = __('Address was resolved to a building base address and requires suite or unit number', $this->id);
			}

			if (!empty($attributes['MissingOrAmbiguousDirectional'])) {
				$addressValidation['errors'][] = __('Address is missing a required leading or trailing directional', $this->id);
			}

			if (!empty($attributes['MissingOrAmbiguousDirectional'])) {
				$addressValidation['errors'][] = __('Address is missing a required leading or trailing directional', $this->id);
			}

			if (!empty($attributes['MultiUnitBase'])) {
				$addressValidation['errors'][] = __('Address was resolved to a standardized address for the base address of a multiunit building', $this->id);
			}

			if (!empty($attributes['MultipleMatches'])) {
				$addressValidation['errors'][] = __('More than one potential matches for provided address', $this->id);
			}

			if (!empty($attributes['MultipleMatches'])) {
				$addressValidation['errors'][] = __('More than one potential matches for provided address', $this->id);
			}			
		}

		$newResponse['addressValidation'] = $addressValidation;

		$errorMessage = $this->getErrorMessage($response['AddressValidationReply']);
		if (!empty($errorMessage)) {
			$newResponse['error']['message'] = $errorMessage;
		}

		return $newResponse;
	}

	protected function parseEffectiveAddress(array $addressResults)
	{
		if (empty($addressResults['EffectiveAddress'])) {
			return array();
		}

		$address = array();
		$effectiveAddress = $addressResults['EffectiveAddress'];
		
		if (!empty($effectiveAddress['CountryCode'])) {
			$address['country'] = $effectiveAddress['CountryCode'];
		}

		if (!empty($effectiveAddress['PostalCode'])) {
			$address['postcode'] = $effectiveAddress['PostalCode'];
		}

		if (!empty($effectiveAddress['StateOrProvinceCode'])) {
			$address['state'] = $effectiveAddress['StateOrProvinceCode'];
		}

		if (!empty($effectiveAddress['City'])) {
			$address['city'] = $effectiveAddress['City'];
		}

		if (!empty($effectiveAddress['StreetLines'])) {
			$address['address'] = current((array)$effectiveAddress['StreetLines']);
		}

		if (!empty($effectiveAddress['StreetLines']) && is_array($effectiveAddress['StreetLines']) && count($effectiveAddress['StreetLines']) > 1) {
			$address['address_2'] = $effectiveAddress['StreetLines'][1];
		}

		return $address;
	}

	protected function parseAddressAttributes(array $addressResults)
	{
		if (empty($addressResults['Attributes']) || !is_array($addressResults['Attributes'])) {
			return array();
		}

		$attributes = array();
		foreach ($addressResults['Attributes'] as $attr) {
			$attributes[$attr['Name']] = filter_var($attr['Value'], FILTER_VALIDATE_BOOLEAN);
		}

		return $attributes;
	}

	protected function getResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getResponse');

		$newResponse = array('response' => $response, 'params' => $params);

		if (empty($response)) {
			$newResponse['error']['message'] = __('FedEx API did not return any response', $this->id);
		} else if (!empty($response['Fault']['detail']['desc'])) {
			$newResponse['error']['message'] = $response['Fault']['detail']['desc'];
		}

		$function = '';
		if (!empty($params['function'])) {
			$function = $params['function'];
		}

		if ($function == 'getRates') {
			$newResponse = array_replace_recursive($newResponse, $this->getRatesResponse($response, $params));
		} else if ($function == 'validateAddressForAccount') {
			$newResponse = array_replace_recursive($newResponse, $this->getValidateAddressResponse($response, $params));
		}

		return $newResponse;
	}

	protected function getErrorMessage(array $response)
	{
		if (empty($response['Notifications']) && empty($response['Notification'])) {
			return null;
		}

		$notifications = array();
		if (isset($response['Notifications'])) {
			if (isset($response['Notifications'][0])) {
				$notifications = $response['Notifications'];
			} else {
				$notifications = array($response['Notifications']);
			}	
		} else if (isset($response['Notification'])) {
			$notifications = array($response['Notification']);
		}

		$message = '';
		foreach ($notifications as $notice) {
			if (!in_array($notice['Severity'], array('SUCCESS', 'NOTE'))) {
				if (!empty($message)) {
					$message .= "\n";
				}

				$message .= $notice['Message'];
			}
		}

		return $message;
	}

	protected function sendSoapRequest(array $params)
	{
		return $this->sendRequest('', 'POST', $params);		
	}

	protected function getRouteUrl($route)
	{
		$routeUrl = '';
		if ($this->sandbox) {
			$routeUrl = 'https://wsbeta.fedex.com:443/web-services';
		} else {
			$routeUrl = 'https://ws.fedex.com/web-services';
		}

		return $routeUrl;
	}

	protected function getRequestBody(&$headers, &$params)
	{
		$body = null;

		if (is_array($params) && count($params) == 2) {
			$this->soapRequestBuilder->setNamespace('', $params[0]);
			$body = $this->soapRequestBuilder->build($params[1]);
		}

		return trim($body);
	}

	protected function parseResponse($response)
	{
		if (is_string($response)) {
			$response = $this->soapResponseParser->parse($response);
			if (isset($response['Envelope']['Body'])) {
				$response = $response['Envelope']['Body'];
			}
		}

		return $response;
	}

	protected function initPackageTypes()
	{
		$this->packageTypes = array(
			'YOUR_PACKAGING' => 'Parcel',
			'FEDEX_ENVELOPE' => 'FedEx Envelope',
			'FEDEX_BOX' => 'FedEx Box',
			'FEDEX_PAK' => 'FedEx Pak',
			'FEDEX_TUBE' => 'FedEx Tube',
			'FEDEX_10KG_BOX' => 'FedEx 10kg Box',
			'FEDEX_25KG_BOX' => 'FedEx 25kg Box',
			'FEDEX_SMALL_BOX' => 'FedEx Small Box',
			'FEDEX_MEDIUM_BOX' => 'FedEx Medium Box',
			'FEDEX_LARGE_BOX' => 'FedEx Large Box',
			'FEDEX_EXTRA_LARGE_BOX' => 'FedEx Extra Large Box',
		);

		$this->_defaultPackageType = current(array_keys($this->packageTypes));

		$this->_fedexOneRatePackageTypes = array(
			'FEDEX_SMALL_BOX' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_MEDIUM_BOX' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_LARGE_BOX' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_EXTRA_LARGE_BOX' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_PAK' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_TUBE' => array('LB' => 50, 'KG' => 22.68),
			'FEDEX_ENVELOPE' => array('LB' => 10, 'KG' => 4.5)
		);

		$this->_freightPackageTypes = array(
			'BAG' => __('Freight Bag', $this->id),
			'BARREL' => __('Freight Barrel', $this->id),
			'BOX' => __('Freight Box', $this->id),
			'BUCKET' => __('Freight Bucket', $this->id),
			'BUNDLE' => __('Freight Bundle', $this->id),
			'CARTON' => __('Freight Carton', $this->id),
			'CASE' => __('Freight Case', $this->id),
			'CONTAINER' => __('Freight Container', $this->id),
			'CRATE' => __('Freight Crate', $this->id),
			'CYLINDER' => __('Freight Cylinder', $this->id),
			'DRUM' => __('Freight Drum', $this->id),
			'ENVELOPE' => __('Freight Envelope', $this->id),
			'HAMPER' => __('Freight Hamper', $this->id),
			'OTHER' => __('Freight Other', $this->id),
			'PAIL' => __('Freight Pail', $this->id),
			'PALLET' => __('Freight Pallet', $this->id),
			'PIECE' => __('Freight Piece', $this->id),
			'REEL' => __('Freight Reel', $this->id),
			'ROLL' => __('Freight Roll', $this->id),
			'SKID' => __('Freight Skid', $this->id),
			'TANK' => __('Freight Tank', $this->id),
			'TUBE' => __('Freight Tube', $this->id),
		);

		$this->packageTypes += $this->_freightPackageTypes;
	}

	protected function initServices()
	{
		$this->_services = array(
			// Regular
			'FEDEX_GROUND' => 'FedEx Ground',
			'FEDEX_2_DAY' => 'FedEx 2 Day',
			'FEDEX_2_DAY_AM' => 'Fedex 2 Day AM',
			'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
			'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
			'FIRST_OVERNIGHT' => 'FedEx First Overnight',
			'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
			'SAME_DAY' => 'FedEx Same Day',
			'SAME_DAY_CITY' => 'FedEx Same Day City',
			'SAME_DAY_METRO_AFTERNOON' => 'FedEx Same Day Metro Afternoon',
			'SAME_DAY_METRO_MORNING' => 'FedEx Same Day Metro Morning',
			'SAME_DAY_METRO_RUSH' => 'FedEx Same Day Metro Rush',
			'FEDEX_NEXT_DAY_AFTERNOON' => 'FedEx Next Day Afternoon',
			'FEDEX_NEXT_DAY_EARLY_MORNING' => 'FedEx Next Day Early Morning',
			'FEDEX_NEXT_DAY_END_OF_DAY' => 'FedEx Next Day End of Day',
			'FEDEX_NEXT_DAY_MID_MORNING' => 'FedEx Next Day Mid Morning',

			'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
			'INTERNATIONAL_ECONOMY_DISTRIBUTION' => 'FedEx International Economy Distribution',
			'INTERNATIONAL_FIRST' => 'FedEx International First',
			'INTERNATIONAL_GROUND' => 'FedEx Internation Ground',
			'INTERNATIONAL_PRIORITY_DISTRIBUTION' => 'FedEx International Priority Distribution',

			'FEDEX_INTERNATIONAL_PRIORITY' => 'FedEx International Priority #1',
			'INTERNATIONAL_PRIORITY' => 'FedEx International Priority #2',
			'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx International Priority Plus',
			'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
			'FEDEX_INTERNATIONAL_CONNECT_PLUS' => 'FedEx International Connect Plus',
			'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'FedEx Europe First International Priority',

			'GROUND_HOME_DELIVERY' => 'FedEx Ground Home Delivery',	
			'FEDEX_REGIONAL_ECONOMY' => 'FedEx Regional Economy',

			// Smart Post
			'SMART_POST' => 'FedEx SmartPost',

			'FEDEX_DISTANCE_DEFERRED' => 'FedEx Distance Deferred',

			// Freight
			'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
			'FEDEX_FREIGHT_ECONOMY' => 'FedEx Freight Economy',
			'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
			'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
			'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
			'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
			'FEDEX_1_DAY_FREIGHT' => 'FedEx 1 Day Freight',
			'FEDEX_2_DAY_FREIGHT' => 'FedEx 2 Day Freight',
			'FEDEX_3_DAY_FREIGHT' => 'FedEx 3 Day Freight',
			'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
			'FEDEX_NEXT_DAY_FREIGHT' => 'FedEx Next Day Freight',
			'FEDEX_REGIONAL_ECONOMY_FREIGHT' => 'FedEx Regional Economy Freight',
			'INTERNATIONAL_ECONOMY_FREIGHT' => 'FedEx Economy Freight',
			'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx International Priority Freight',
			'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Freight',

			// Cargo
			'FEDEX_CARGO_AIRPORT_TO_AIRPORT' => 'FedEx Cargo Airport to Airpot',
			'FEDEX_CARGO_FREIGHT_FORWARDING' => 'FedEx Cargo Freight Forwarding',
			'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT' => 'FedEx Cargo International Express Freight',
			'FEDEX_CARGO_INTERNATIONAL_PREMIUM' => 'FedEx Cargo International Premium',
			'FEDEX_CARGO_MAIL' => 'FedEx Cargo Mail',
			'FEDEX_CARGO_REGISTERED_MAIL' => 'FedEx Cargo Registered Mail',
			'FEDEX_CARGO_SURFACE_MAIL' => 'FedEx Cargo Surface Mail',

			// Custom
			'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE' => 'FedEx Custom Critical Air Expedite',
			'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE' => 'FedEx Custom Critical Air Expedite Exclusive Use',
			'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK' => 'FedEx Custom Critical Air Expedite Network',
			'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR' => 'FedEx Custom Critical Charter Air',
			'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT' => 'FedEx Custom Critical Point to Point',
			'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE' => 'FedEx Custom Critical Surface Expedite',
			'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR' => 'FedEx Custom Critical Temp Assure Air',
			'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR' => 'FedEx Custom Critical Temp Assure Validated Air',
			'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES' => 'FedEx Custom Critical White Glove Services',

			'TRANSBORDER_DISTRIBUTION_CONSOLIDATION' => 'FedEx Transborder Distribution Consolidation'
		);
	}

	protected function getAccountNumber()
	{
		return $this->testAccountNumber;		
	}

	protected function getUserCredential()
	{
		$params = array();
		$params['Key'] = $this->key;
		$params['Password'] = $this->password;

		return $params;
	}

	protected function getClientDetail(array $inParams)
	{
		$params = array();
		if (!empty($inParams['clientDetail']) && is_array($inParams['clientDetail'])) {
			$params = $inParams['clientDetail'];
		}

		return $params;
	}

	protected function getVersion($serviceId, $major)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getVersion: ' . $serviceId . ', ' . $major);

		$version = array();
		$version['ServiceId'] = $serviceId;
		$version['Major'] = $major;
		$version['Intermediate'] = 0;
		$version['Minor'] = 0;
		
		return $version;
	}

	protected function getRequestedShipment(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRequestedShipment');

		$requestedShipment = array();
		$requestedShipment['ShipTimestamp'] = date('c');
		$requestedShipment['DropoffType'] = 'REGULAR_PICKUP';
		$serviceType = $this->getServiceType($inParams);
		if (!empty($serviceType)) {
			$requestedShipment['ServiceType'] = $serviceType;
		}
		$requestedShipment['PackagingType'] = $this->getPackagingType($inParams);

		if (empty($inParams['accountType']) || $inParams['accountType'] != 'freight') {
			$requestedShipment['TotalWeight'] = $this->getWeight($inParams);
			$totalInsuredValue = $this->getInsuredValue($inParams);
			if (!empty($totalInsuredValue)) {
				$requestedShipment['TotalInsuredValue'] = $totalInsuredValue;
			}				
		}

		$requestedShipment['PreferredCurrency'] = $this->getCurrency($inParams);
		$requestedShipment += $this->getShipperAndRecipient($inParams);
		$requestedShipment['ShippingChargesPayment'] = $this->getShippingChargesPayment($inParams);

		$freightShipmentDetail = $this->getFreightShipmentDetail($inParams);
		if (!empty($freightShipmentDetail)) {
			$requestedShipment['FreightShipmentDetail'] = $freightShipmentDetail;
		}	

		$customsClearanceDetail = $this->getCustomsInfo($inParams);
		if (!empty($customsClearanceDetail)) {
			$requestedShipment['CustomsClearanceDetail'] = $customsClearanceDetail;
		}

		$smartPostDetail = $this->getSmartPostDetail($inParams);
		if (!empty($smartPostDetail)) {
			$requestedShipment['SmartPostDetail'] = $smartPostDetail;
		}	

		$labelSpecification = $this->getLabelSpecification($inParams);
		if (!empty($labelSpecification)) {
			$requestedShipment['LabelSpecification'] = $labelSpecification;
		}
				
		$shippingDocumentSpecification = $this->getShippingDocumentSpecification($inParams);
		if (!empty($shippingDocumentSpecification)) {
			$requestedShipment['ShippingDocumentSpecification'] = $shippingDocumentSpecification;
		}

		$requestedShipment['RateRequestTypes'] = 'LIST';
		$requestedShipment['PackageCount'] = 1;

		$requestedPackageLineItems = $this->getRequestedPackageLineItems($inParams);
		if (!empty($requestedPackageLineItems)) {
			$requestedShipment['RequestedPackageLineItems'] = $requestedPackageLineItems;
		}	

		return $requestedShipment;
	}

	protected function getLabelSpecification(array $inParams)
	{
		return null;
	}

	protected function getShippingDocumentSpecification(array $inParams)
	{
		return null;
	}

	protected function getDaysDiff($time1, $time2)
	{
		$time1 = strtotime($time1);
		$time2 = strtotime($time2);

		$timeDiff = $time2 - $time1;

		return round($timeDiff / (60 * 60 * 24));
	}

	protected function getShippingChargesPayment(array $inParams)
	{
		$params = array();
		$params['PaymentType'] = 'SENDER';
		if (!empty($inParams['clientDetail']['AccountNumber'])) {
			$params['Payor']['ResponsibleParty']['AccountNumber'] = $inParams['clientDetail']['AccountNumber'];
		}

		return $params;
	}

	protected function getPackagingType(array $inParams)
	{
		$packageType = '';
		if (!empty($inParams['accountType']) && $inParams['accountType'] == 'freight') {
			$packageType = 'YOUR_PACKAGING';
		} else if (!empty($inParams['type']) && isset($this->_freightPackageTypes[$inParams['type']])) {
			$packageType = 'YOUR_PACKAGING';
		}

		if (empty($packageType)) {
			if (!empty($inParams['type']) && isset($this->packageTypes[$inParams['type']])) {
				$packageType = $inParams['type'];
			} else {
				$packageType = current(array_keys($this->packageTypes));
			}	
		}

		$this->logger->debug(__FILE__, __LINE__, 'Package Type: ' . $packageType);

		return $packageType;
	}

	protected function getSpecialServicesRequested(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getSpecialServicesRequested');

		$specialServiceTypes = $this->getSpecialServiceTypes($inParams);
		if (empty($specialServiceTypes)) {
			return array();
		}

		$params = array();
		$params['SpecialServiceTypes'] = $specialServiceTypes;

		if ($this->isSignatureRequested($inParams)) {
			$params['SignatureOptionDetail']['OptionType'] = 'DIRECT';
		}

		return $params;
	}

	protected function getSpecialServiceTypes(array $inParams)
	{
		$specialServiceTypes = array();

		$packageType = $this->getPackagingType($inParams);
		if (!empty($this->_fedexOneRatePackageTypes[$packageType])) {
			$oneRateMaxWeight = $this->_fedexOneRatePackageTypes[$packageType];
			$weight = $this->getWeight($inParams);

			$this->logger->debug(__FILE__, __LINE__, 'Verify One Rate Max Weight requirement: ' . print_r($oneRateMaxWeight, true));

			if (!empty($weight['Units']) && !empty($oneRateMaxWeight[$weight['Units']]) && $weight['Value'] <= $oneRateMaxWeight[$weight['Units']]) {
				$this->logger->debug(__FILE__, __LINE__, 'Package qualifies for FedEx One Rate');

				$specialServiceTypes[] = 'FEDEX_ONE_RATE';
			}
		}

		if ($this->isSignatureRequested($inParams)) {
			$specialServiceTypes[] = 'SIGNATURE_OPTION';
		}

		return $specialServiceTypes;
	}

	protected function getContentsDescription(array $inParams)
	{
		$description = '';
		if (!empty($inParams['description'])) {
			$description = $inParams['description'];
		}

		$description = __('Merchandise', $this->id);

		return $description;
	}

	protected function getValue(array $inParams)
	{
		$value = 0;
		if (!empty($inParams['value'])) {
			$value = $inParams['value'];
		}

		$params = array();
		$params['Currency'] = $this->getCurrency($inParams);
		$params['Amount'] = $value;

		return $params;
	}

	protected function getInsuredValue(array $inParams)
	{
		$params = $this->getValue($inParams);

		if ($this->isInsuranceRequested($inParams) && !empty($inParams['value'])) {
			$params['Amount'] = $inParams['value'];
		} else {
			$params = array();
		}

		return $params;
	}

	protected function getCurrency(array $inParams)
	{
		$currency = '';

		if (isset($inParams['currency']) && isset($this->currencies[$inParams['currency']])) {
			$currency = $inParams['currency'];
		} else {
			$currency = current(array_keys($this->currencies));
		}
		
		return $currency;
	}

	protected function getWeight(array $inParams)
	{
		$weight = 0;
		if (!empty($inParams['weight'])) {
			$weight = $inParams['weight'];
		}

		$weightUnit = $this->weightUnit;

		if (isset($inParams['weight_unit'])) {
			$weightUnit = $inParams['weight_unit'];
		}

		if (!in_array($weightUnit, array('lbs'))) {
			$this->logger->debug(__FILE__, __LINE__, 'Our weight unit is in ' . $weightUnit . ', so convert it to LB');	
			$fromWeightUnit = $weightUnit;
			$weightUnit = 'lbs';

			$weight = wc_get_weight($weight, $weightUnit, $fromWeightUnit);
		}

		if ($weightUnit == 'lbs') {
			$weightUnit = 'lb';
		}

		return array('Units' => strtoupper($weightUnit), 'Value' => round($weight, 2));
	}

	protected function getDimensions(array $inParams)
	{
		$dimensionUnit = $this->dimensionUnit;
		if (!empty($inParams['dimension_unit'])) {
			$dimensionUnit = $inParams['dimension_unit'];
		}

		$fromDimensionUnit = $dimensionUnit;
		if (!in_array($dimensionUnit, array('in'))) {
			$dimensionUnit = 'in';
		}

		$params = array();
		$params['Length'] = round(wc_get_dimension($inParams['length'], $dimensionUnit, $fromDimensionUnit));
		$params['Width'] = round(wc_get_dimension($inParams['width'], $dimensionUnit, $fromDimensionUnit));
		$params['Height'] = round(wc_get_dimension($inParams['height'], $dimensionUnit, $fromDimensionUnit));
		$params['Units'] = strtoupper($dimensionUnit);

		return $params;
	}

	protected function getFreightClass(array $inParams)
	{
		$freightClass = 500;
		if (!empty($inParams['freightClass'])) {
			$freightClass = $inParams['freightClass'];
		}

		$freightClassName = 'CLASS_';

		if ($freightClass < 100) {
			$freightClassName .= '0';
		}

		$freightClassName .= str_replace('.', '_', $freightClass . '');

		$this->logger->debug(__FILE__, __LINE__, 'Freight Class: ' . $freightClassName);

		return $freightClassName;
	}

	protected function getOrderNumber(array $inParams)
	{
		$orderNumber = '';
		if (!empty($inParams['order_number'])) {
			$orderNumber = $inParams['order_number'];
		} else if (!empty($inParams['order_id'])) {
			$orderNumber = $inParams['order_id'];
		}

		return $orderNumber;
	}

	protected function getServiceType(array $inParams)
	{
		$serviceType = null;
		if (!empty($inParams['serviceType']) && !empty($this->_services[$inParams['serviceType']])) {
			$serviceType = $inParams['serviceType'];
		} else if (!empty($inParams['accountType']) && $inParams['accountType'] == 'smartpost') {
			if (!empty($inParams['destination']['country']) && $inParams['destination']['country'] == 'US') {
				$serviceType = 'SMART_POST';
			}
		}

		return $serviceType;
	}

	protected function canAddSmartPostDetail(array $inParams)
	{
		if (!empty($inParams['accountType']) && $inParams['accountType'] == 'smartpost') {
			return true;
		}

		return false;
	}

	protected function getSmartPostDetail(array $inParams)
	{
		if (!$this->canAddSmartPostDetail($inParams)) {
			return null;
		}

		$this->logger->debug(__FILE__, __LINE__, 'getSmartPostDetail');

		$params = array();

		$mediaMail = $this->getRequestedMediaMail($inParams);

		$isReturn = false;
		if (!empty($inParams['return']) && filter_var($inParams['return'], FILTER_VALIDATE_BOOLEAN)) {
			$isReturn = true;
		}

		//if ($isReturn) {
		//	$params['Indicia'] = 'PARCEL_RETURN';
		//	$params['AncillaryEndorsement'] = 'CARRIER_LEAVE_IF_NO_RESPONSE';
		//} else
		if (!empty($mediaMail) && $mediaMail != 'exclude') {
			$params['Indicia'] = 'MEDIA_MAIL';
			$params['AncillaryEndorsement'] = 'ADDRESS_CORRECTION';
		} else {
			$weightUnit = $this->weightUnit;
			if (isset($inParams['weight_unit'])) {
				$weightUnit = $inParams['weight_unit'];
			}

			if (!empty($inParams['weight'])) {
				$weight = wc_get_weight($inParams['weight'], 'lbs', $weightUnit);
			}

			if ($weight > 1) {
				$params['Indicia'] = 'PARCEL_SELECT';
			} else {
				$params['Indicia'] = 'PRESORTED_STANDARD';
			}
			
			$params['AncillaryEndorsement'] = 'ADDRESS_CORRECTION';
			$params['SpecialServices'] = 'USPS_DELIVERY_CONFIRMATION';
		}

		if (!empty($inParams['HubId'])) {
			$params['HubId'] = $inParams['HubId'];
		}

		$params['CustomerManifestId'] = $this->getOrderNumber($inParams);

		return $params;
	}

	protected function canAddFreightShipmentDetail(array $inParams)
	{
		if (!empty($inParams['accountType']) && $inParams['accountType'] == 'freight') {
			return true;
		}

		return false;
	}

	protected function getFreightShipmentDetail(array $inParams)
	{
		if (!$this->canAddFreightShipmentDetail($inParams)) {
			return null;
		}

		$this->logger->debug(__FILE__, __LINE__, 'getFreightShipmentDetail');

		$billingAddress = null;
		if (!empty($inParams['address']['sameAsFromAddress'])) {
			$billingAddress = $inParams['origin'];
		} else {
			$billingAddress = $inParams['address'];
		}
		
		$packageType = '';
		if (!empty($inParams['type']) && isset($this->_freightPackageTypes[$inParams['type']])) {
			$packageType = $inParams['type'];
		} else if (!empty($inParams['defaultFreightPackageType'])) {
			$packageType = $inParams['defaultFreightPackageType'];
		} else {
			$packageType = current(array_keys($this->_freightPackageTypes));
		}

		$params = array();

		$params['FedExFreightAccountNumber'] = $inParams['FedExFreightAccountNumber'];
		$params['FedExFreightBillingContactAndAddress'] = $this->getAddress($billingAddress);
		$params['Role'] = 'SHIPPER';
		$params['DeclaredValuePerUnit'] = $this->getValue($inParams);
		$params['DeclaredValueUnits'] = 1;
		$params['LiabilityCoverageDetail']['CoverageType'] = 'USED_OR_RECONDITIONED';
		$params['LiabilityCoverageDetail']['CoverageAmount'] = $this->getInsuredValue($inParams);
		$params['TotalHandlingUnits'] = 1;
		$params['PalletWeight'] = $this->getWeight($inParams);
		$params['ShipmentDimensions'] = $this->getDimensions($inParams);
		$params['LineItems']['FreightClass'] = $this->getFreightClass($inParams);
		$params['LineItems']['HandlingUnits'] = 1;
		$params['LineItems']['Packaging'] = $packageType;
		$params['LineItems']['Pieces'] = 1;
		$params['LineItems']['PurchaseOrderNumber'] = $this->getOrderNumber($inParams);
		$params['LineItems']['Description'] = $this->getContentsDescription($inParams);
		$params['LineItems']['Weight'] = $this->getWeight($inParams);
		$params['LineItems']['Dimensions'] = $this->getDimensions($inParams);

		return $params;
	}

	protected function canAddRequestedPackageLineItems(array $inParams)
	{
		if (!empty($inParams['accountType']) && in_array($inParams['accountType'], array('express', 'smartpost'))) {
			return true;
		}

		return false;
	}

	protected function getRequestedPackageLineItems(array $inParams)
	{
		if (!$this->canAddRequestedPackageLineItems($inParams)) {
			return null;
		}

		$this->logger->debug(__FILE__, __LINE__, 'getRequestedPackageLineItems');

		$params = array();

		$params['SequenceNumber'] = 1;
		$params['GroupNumber'] = 1;
		$params['GroupPackageCount'] = 1;
		$params['Weight'] = $this->getWeight($inParams);
		$params['Dimensions'] = $this->getDimensions($inParams);
		$params['ItemDescription'] = $this->getContentsDescription($inParams);

		$customerReference = $this->getCustomerReference($inParams);
		if (!empty($customerReference)) {
			$params['CustomerReferences'] = $customerReference;
		}

		$specialServicesRequested = $this->getSpecialServicesRequested($inParams);
		if (!empty($specialServicesRequested)) {
			$params['SpecialServicesRequested'] = $specialServicesRequested;
		}

		$params['ContentRecords']['Description'] = $this->getContentsDescription($inParams);

		return $params;
	}

	protected function getCustomerReference(array $inParams)
	{
		$params = array();

		if (!empty($inParams['order_number'])) {
			$params['CustomerReferenceType'] = 'CUSTOMER_REFERENCE';
			$params['Value'] = $inParams['order_number'];
		}

		return $params;
	}

	protected function getCustomsInfo(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getCustomsInfo');

		if (isset($inParams['origin']['country']) &&
			isset($inParams['destination']['country']) &&
			$inParams['origin']['country'] == $inParams['destination']['country']) {
			$this->logger->debug(__FILE__, __LINE__, 'Order is local, so no need for customs info');

			return null;
		}

		$customsInfo = array();
		$customsInfo['CustomsValue'] = $this->getValue($inParams);

		if (!empty($inParams['contents']) && !empty($this->contentTypes[$inParams['contents']])) {
			$customsInfo['CommercialInvoice']['Purpose'] = $inParams['contents'];
		}

		if (!empty($inParams['items']) && is_array($inParams['items'])) {
			$customsInfo['Commodities'] = $this->getCustomsItems($inParams['items'], strtoupper($inParams['origin']['country']), $this->getCurrency($inParams));
		}

		return $customsInfo;
	}

	protected function getCustomsItems(array $itemsInParcel, $defaultOriginCountry, $currency)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getCustomsItems');

		$customsItems = array();

		foreach ($itemsInParcel as $itemInParcel) {
			if (empty($itemInParcel['country'])) {
				$itemInParcel['country'] = $defaultOriginCountry;
			}

			$itemInParcel['currency'] = $currency;

			$customsItem = $this->getCustomsItem($itemInParcel);
			if (!empty($customsItem)) {
				$customsItems[] = $customsItem;
			}
		}
		
		return $customsItems;
	}

	protected function getCustomsItem($itemInParcel)
	{
		if (empty($itemInParcel['name']) || 
			!isset($itemInParcel['weight']) || 
			empty($itemInParcel['quantity']) ||
			!isset($itemInParcel['value'])) {
			$this->logger->debug(__FILE__, __LINE__, 'Item is invalid, so skip it ' . print_r($itemInParcel, true));

			return false;
		}

		$customsItem = array();
		$customsItem['NumberOfPieces'] = 1;
		$customsItem['Description'] = substr($itemInParcel['name'], 0, min(self::MAX_DESCRIPTION_LENGTH, strlen($itemInParcel['name'])));
		$customsItem['CountryOfManufacture'] = trim($itemInParcel['country']);
		$customsItem['Weight'] = $this->getWeight($itemInParcel);
		$customsItem['Quantity'] = $itemInParcel['quantity'];
		$customsItem['QuantityUnits'] = 'PCS';
		$customsItem['UnitPrice'] = $this->getValue($itemInParcel);
		$customsItem['CustomsValue']['Currency'] = $this->getCurrency($itemInParcel);
		$customsItem['CustomsValue']['Amount'] = $itemInParcel['value'] * $itemInParcel['quantity'];

		return $customsItem;
	}
	
	protected function getShipAddress(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getShipAddress');

		$response = $this->validateAddress($inParams);
		$inParams['residential'] = !empty($response['addressValidation']['residential']);

		return $this->getAddress($inParams);
	}

	protected function getAddress(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getAddress');

		$addr = array();

		if (!empty($inParams['name'])) {
			$addr['Contact']['PersonName'] = $inParams['name'];
		} else if (empty($inParams['company'])) {
			$addr['Contact']['PersonName'] = 'Resident';
		}

		if (!empty($inParams['company'])) {
			$addr['Contact']['CompanyName'] = $inParams['company'];
		}

		if (!empty($inParams['phone'])) {
			if (is_array($inParams['phone'])) {
				$inParams['phone'] = current($inParams['phone']);
			}
			
			$addr['Contact']['PhoneNumber'] = $inParams['phone'];
		}

		if (!empty($inParams['email'])) {
			$addr['Contact']['EMailAddress'] = $inParams['email'];
		}

		if (!empty($inParams['address'])) {
			$addr['Address']['StreetLines'][] = $inParams['address'];
		} else {
			$addr['Address']['StreetLines'][] = '';
		}

		if (!empty($inParams['address_2'])) {
			$addr['Address']['StreetLines'][] = $inParams['address_2'];
		}

		if (!empty($inParams['city'])) {
			$addr['Address']['City'] = $inParams['city'];
		} else {
			$addr['Address']['City'] = '';
		}

		if (!empty($inParams['state'])) {
			$addr['Address']['StateOrProvinceCode'] = $inParams['state'];
		} else {
			$addr['Address']['StateOrProvinceCode'] = '';
		}

		if (!empty($inParams['postcode'])) {
			$addr['Address']['PostalCode'] = $inParams['postcode'];
		} else {
			$addr['Address']['PostalCode'] = '';
		}

		if (!empty($inParams['country'])) {
			$addr['Address']['CountryCode'] = strtoupper($inParams['country']);
		} else {
			$addr['Address']['CountryCode'] = '';
		}

		if (isset($inParams['residential'])) {
			$addr['Address']['Residential'] = $inParams['residential'];
		} else if (empty($inParams['company'])) {
			$addr['Address']['Residential'] = true;
		}

		return $addr;
	}

	protected function getShipperAndRecipient(array $inParams)
	{
		$params = array();

		$fromKey = 'origin';
		$toKey = 'destination';

		if (!empty($inParams['return'])) {
			$fromKey = 'destination';
			$toKey = 'origin';
		}

		if (empty($inParams['origin']) && !empty($this->origin)) {
			$inParams['origin'] = $this->origin;
		}

		$params['Shipper'] = $this->getShipAddress($inParams[$fromKey]);
		$params['Recipient'] = $this->getShipAddress($inParams[$toKey]);

		return $params;
	}

	protected function getRequestedMediaMail(array $inParams)
	{
		$mediaMail = $this->mediaMail;
		if (isset($inParams['mediaMail'])) {
			$mediaMail = $inParams['mediaMail'];
		}

		return $mediaMail;
	}
}

endif;
