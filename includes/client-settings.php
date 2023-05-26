<?php
/**
 * Client Settings
 *
 * @package WooCommerce/Classes/Payment
 */

defined('ABSPATH') || exit;

return array(
	'enabled' => array(
		'title' => __('Enable', 'gocrypto_pay'),
		'type' => 'checkbox',
		'label' => __('Enable', 'gocrypto_pay'),
		'default' => 'yes'
	),
	'host' => array(
		'title' => __('Host', 'gocrypto_pay'),
		'type' => 'text',
		'default' => '',
		'custom_attributes' => array(
			'readonly' => 'readonly'
		)
	),
	'client_id' => array(
		'title' => __('Client ID', 'gocrypto_pay'),
		'type' => 'text',
		'default' => '',
		'custom_attributes' => array(
			'readonly' => 'readonly'
		)
	),
	'client_secret' => array(
		'title' => __('Client Secret', 'gocrypto_pay'),
		'type' => 'password',
		'default' => '',
		'custom_attributes' => array(
			'readonly' => 'readonly'
		)
	),
	'sandbox' => array(
		'title' => __('Sandbox', 'gocrypto_pay'),
		'type' => 'checkbox',
		'default' => 'no'
	),
);
