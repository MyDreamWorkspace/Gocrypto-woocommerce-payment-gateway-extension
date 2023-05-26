<?php
// Author (c) Daniel Jaušovec, Eligma Ltd.
// Version: GOC-001:2022-07-07

namespace Eligmaltd\GoCryptoPayPHP;

class GoCryptoCommon {
	/**
	 * Random numbers generator
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public function randomNumbers($length = 10) {
		$characters = '0123456789';
		$charactersLength = strlen($characters);
		$randomNumbers = '';
		for ($i = 0; $i < $length; $i++) {
			$randomNumbers .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomNumbers;
	}

	/**
	 * Random string generator
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public function randomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	/**
	 * Generate payment token
	 *
	 * @return string
	 */
	public function generatePaymentToken() {
		return md5($this->randomString(48));
	}

	/**
	 * Parse payment methods from array and return as a string separated with comma
	 *
	 * @param array $paymentMethods
	 *
	 * @return string
	 */
	public function getPaymentMethodsAsString(array $paymentMethods) {
		$text = '';
		if (!empty($paymentMethods)) {
			foreach ($paymentMethods as $paymentMethod) {
				$text .= $paymentMethod['name'] . ', ';
			}
		}
		return rtrim($text, ', ');
	}
}
