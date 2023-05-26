<?php
/**
 * Pair Settings
 *
 * @package WooCommerce/Classes/Payment
 */

defined('ABSPATH') || exit;

return array(
	'host' => array(
		'title' => __('Host', 'gocrypto_pay'),
		'type' => 'text',
		'default' => ''
	),
	'otp' => array(
		'title' => __('OTP', 'gocrypto_pay'),
		'type' => 'text',
		'default' => ''
	),
	'terminal_id' => array(
		'title' => __('Terminal ID', 'gocrypto_pay'),
		'type' => 'text',
		'default' => ''
	),
	'sandbox' => array(
		'title' => __('Sandbox', 'gocrypto_pay'),
		'type' => 'checkbox',
		'label' => __('Enable sandbox', 'gocrypto_pay'),
		'default' => 'no',
		'description' => __('Sandbox can be used to test payments'),
	),
);
