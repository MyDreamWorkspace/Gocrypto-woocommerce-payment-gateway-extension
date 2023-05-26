<?php
// Author (c) Daniel Jaušovec, Eligma Ltd.
// Version: GOC-001:2022-07-07

namespace Eligmaltd\GoCryptoPayPHP;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

class GoCryptoAuth {
	public $logger;
	public $common;
	public $client;
	public $endpoint;
	public $clientId;
	public $clientSecret;
	public $accessToken;

	public function __construct($endpoint, $clientID, $clientSecret) {
		$this->logger = new GoCryptoLogger();
		$this->common = new GoCryptoCommon();
		$this->client = new GuzzleHttp\Client();

		$this->endpoint = $endpoint;
		$this->clientId = $clientID;
		$this->clientSecret = $clientSecret;
	}

	/**
	 * Get access token
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function getAccessToken() {
		try {
			$response = $this->client->request('POST', $this->endpoint . '/auth/token/', [
				'headers' => [
					'Content-Type' => 'application/json'
				],
				'json' => [
					'grant_type' => 'client_credentials',
					'client_id' => $this->clientId,
					'client_secret' => $this->clientSecret
				]
			]);

			$responseData = json_decode((string)$response->getBody(), true);
			$this->accessToken = $responseData['access_token'];
			return $this->accessToken;
		} catch (RequestException $e) {
			$this->logger->writeLog($e->getMessage());
			return null;
		}
	}
}
