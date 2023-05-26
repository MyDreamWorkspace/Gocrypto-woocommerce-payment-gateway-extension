<?php
// Author (c) Daniel Jaušovec, Eligma Ltd.
// Version: GOC-001:2022-07-07

namespace Eligmaltd\GoCryptoPayPHP;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

class GoCryptoPay {
	public $logger;
	public $common;
	public $client;
	public $publicEndpoint;
	public $endpoint;
	public $clientId;
	public $clientSecret;
	public $authClient;

	/**
	 * Init
	 *
	 * @param boolean $isTest
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function __construct($isTest = false) {
		$this->logger = new GoCryptoLogger();
		$this->common = new GoCryptoCommon();
		$this->client = new GuzzleHttp\Client();
		$this->publicEndpoint = $isTest ? 'https://public.api.staging.ellypos.io' : 'https://public.api.ellypos.io';
	}

	/**
	 * Set credentials
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 */
	public function setCredentials($clientId, $clientSecret) {
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
	}

	/**
	 * Auth Initialization
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function auth() {
		$this->authClient = new GoCryptoAuth($this->endpoint, $this->clientId, $this->clientSecret);
		return $this->authClient->getAccessToken();
	}

	/**
	 * Config Initialization
	 *
	 * @param string $host
	 *
	 * @return mixed|string
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function config($host) {
		try {
			$response = $this->client->request('GET', $this->publicEndpoint . '/config/web-shop/', [
				'headers' => [
					'Content-Type' => 'application/json',
					'X-WEB-SHOP-HOST' => $host
				]
			]);

			$responseData = json_decode((string)$response->getBody(), true);
			$this->endpoint = $responseData['api_endpoint'];
			return $responseData;
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return $e->getMessage();
		}
	}

	/**
	 * Device Initialization
	 *
	 * @param string $terminalId
	 * @param string $otp
	 * @param string|null $serialNumber
	 *
	 * @return mixed|string
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function pair($terminalId, $otp, $serialNumber = null) {
		try {
			$response = $this->client->request('POST', $this->endpoint . '/devices/pair/', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => [
					'otp' => $otp,
					'terminal_id' => $terminalId,
					'serial_number' => $serialNumber ? $serialNumber : $this->common->randomNumbers(12),
				]
			]);

			return json_decode((string)$response->getBody(), true);
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return $e->getMessage();
		}
	}

	/**
	 * Generate charge
	 *
	 * @param array $data
	 *
	 * @return array|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * @var $data [shop_name] string, required
	 * @var $data [shop_description] string, optional
	 * @var $data [language] string, optional
	 * @var $data [order_number] string, required
	 * @var $data [reference_number] string, optional
	 * @var $data [amount] integer, required
	 * @var $data [discount] integer, optional
	 * @var $data [currency_code] string, optional
	 * @var $data [customer_email] string, required
	 * @var $data [items] array, optional
	 * @var $data [items][name] string, required
	 * @var $data [items][quantity] decimal, optional
	 * @var $data [items][unit] string, optional
	 * @var $data [items][price] integer, optional
	 * @var $data [items][discount] integer, optional
	 * @var $data [items][tax] integer, optional
	 * @var $data [callback_endpoint] string, required
	 */
	public function generateCharge($data = array()) {
		try {
			$language = $data['language'];

			// generate charge
			$chargeData = [
				'shop_name' => $data['shop_name'],
				'shop_description' => $data['shop_description'],
				'order_number' => $data['order_number'],
				'amount' => $data['amount'],
				'customer_email' => $data['customer_email'],
				'callback_endpoint' => $data['callback_endpoint']
			];

			if (array_key_exists('currency_code', $data)) {
				$chargeData['currency_code'] = $data['currency_code'];
			}

			if (array_key_exists('items', $data)) {
				$chargeData['items'] = $data['items'];
			}

			$response = $this->client->request('POST', $this->endpoint . '/payments/charge/', [
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept-Language' => $language ? $language : 'en',
					'Authorization' => 'Bearer ' . $this->authClient->accessToken
				],
				'json' => $chargeData
			]);

			$responseData = json_decode((string)$response->getBody(), true);

			return [
				'charge_id' => $responseData['charge_id'],
				'redirect_url' => $responseData['redirect_url']
			];
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return array();
		}
	}

	/**
	 * Get payment methods
	 *
	 * @return array
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getPaymentMethods() {
		try {
			$response = $this->client->request('GET', $this->endpoint . '/devices/payment-methods/', [
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->authClient->accessToken
				]
			]);

			return json_decode((string)$response->getBody(), true);
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return array();
		}
	}

	/**
	 * Check transaction status
	 *
	 * @param string $transactionId
	 *
	 * @return string
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function checkTransactionStatus($transactionId) {
		try {
			$response = $this->client->request('GET', $this->endpoint . '/transactions/' . $transactionId . '/status/', [
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->authClient->accessToken
				]
			]);

			return json_decode((string)$response->getBody(), true)['status'];
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return null;
		}
	}
}
