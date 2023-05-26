<?php
/**
 * Plugin Name: GoCrypto Pay
 * Description: Instant and secure crypto payments.
 * Version: 1.0.4
 * Author: Eligma Ltd.
 * Author URI: https://gocrypto.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 4.0
 * Tested up to: 6.0
 * WC requires at least: 3.0.0
 * WC tested up to: 6.6.1
 * Text Domain: gocrypto_pay
 * Domain Path: /languages
 */

if (!function_exists('get_plugin_data')) {
	require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

use Eligmaltd\GoCryptoPayPHP\GoCryptoPay;

$plugin_data = get_plugin_data(__FILE__);
define('GOC_PLUGIN_ID', $plugin_data['TextDomain']);
define('GOC_PLUGIN_NAME', $plugin_data['Name']);
define('GOC_PLUGIN_DESCRIPTION', $plugin_data['Description']);
define('GOC_PLUGIN_TEXT_DOMAIN', $plugin_data['TextDomain']);
define('GOC_PLUGIN_VERSION', $plugin_data['Version']);

// init payment class
add_action('plugins_loaded', 'init_goc_pay_class');

function init_goc_pay_class() {
	/**
	 * Payment class
	 */
	class WC_GOC_Pay extends WC_Payment_Gateway {
		private $init_form_type;
		private $gocryptoPay;
		private $badgeIcon;

		/**
		 * Constructor for your shipping class
		 *
		 * @return void
		 */
		public function __construct() {
			$this->id = GOC_PLUGIN_ID;
			$this->method_title = GOC_PLUGIN_NAME;
			$this->method_description = GOC_PLUGIN_DESCRIPTION;
			$this->order_button_text = __('Place order', 'gocrypto_pay');
			$this->description = __('Instant and secure crypto payments.', 'gocrypto_pay');
			$this->has_fields = true;
			$this->init_settings();

			$this->client_id = $this->get_option('client_id');
			if ($this->client_id) {
				// get sandbox info
				$this->is_sandbox = $this->get_option('sandbox') == 'yes' ? true : false;

				// init pay and set config
				$this->gocryptoPay = new GoCryptoPay($this->is_sandbox);
				$config = $this->gocryptoPay->config($this->get_option('host'));

				// set title and badge icon
				$this->title = $config['title'];
				$this->badgeIcon = $config['badge_icon'];

				// set client form
				$this->init_form_type = 'client';
				$this->init_form_fields();
				$this->enabled = $this->get_option('enabled');
				$this->client_secret = $this->get_option('client_secret');

				// init check payment hook
				add_action('goc_check_payment', array($this, 'check_response'));

				/**
				 * Apply filter for icon
				 *
				 * @since Unknown
				 */
				$this->icon = apply_filters($this->id . '_icon', '');
			} else {
				$this->init_form_type = 'pair';
				$this->init_form_fields();
			}

			// save settings
			if (is_admin()) {
				// init admin options hook
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			}
		}

		public function get_icon() {
			$iconHTML = '<img src="' . $this->badgeIcon . '" class="pay-badge" />';
			/**
			 * Apply filter for icon
			 *
			 * @since Unknown
			 */
			return apply_filters('woocommerce_gateway_icon', $iconHTML, $this->id);
		}

		public function init_form_fields() {
			if ('pair' == $this->init_form_type) {
				$this->form_fields = include 'includes/pair-settings.php';
			} else if ('client' == $this->init_form_type) {
				$this->form_fields = include 'includes/client-settings.php';
			}
		}

		public function process_admin_options() {
			// get post data
			$postData = $this->get_post_data();

			if (array_key_exists('woocommerce_' . $this->id . '_host', $postData) &&
				array_key_exists('woocommerce_' . $this->id . '_otp', $postData) &&
				array_key_exists('woocommerce_' . $this->id . '_terminal_id', $postData)) {
				// set test environment
				$isTest = array_key_exists('woocommerce_' . $this->id . '_sandbox', $postData) ? $postData['woocommerce_' . $this->id . '_sandbox'] : false;

				// initialize elly pos pay
				$this->gocryptoPay = new GoCryptoPay($isTest);

				// get form data
				$host = $postData['woocommerce_' . $this->id . '_host'];
				$terminalID = $postData['woocommerce_' . $this->id . '_terminal_id'];
				$otp = $postData['woocommerce_' . $this->id . '_otp'];

				// get config
				$config = $this->gocryptoPay->config($host);
				if (!is_string($config)) {
					// pair virtual device
					$pairResponse = $this->gocryptoPay->pair($terminalID, $otp);
					if (!is_string($pairResponse)) {
						$postData['woocommerce_' . $this->id . '_enabled'] = 'yes';
						$postData['woocommerce_' . $this->id . '_host'] = $host;
						$postData['woocommerce_' . $this->id . '_client_id'] = $pairResponse['client_id'];
						$postData['woocommerce_' . $this->id . '_client_secret'] = $pairResponse['client_secret'];
						$this->init_form_type = 'client';
						$this->init_form_fields();
						$this->set_post_data($postData);
					} else {
						$settings = new WC_Admin_Settings();
						$settings->add_error(__('Something went wrong on pairing!', 'gocrypto_pay'));
					}
				} else {
					$settings = new WC_Admin_Settings();
					$settings->add_error(__('Something went wrong on pairing!', 'gocrypto_pay'));
				}
			}

			return parent::process_admin_options();
		}

		public function process_payment($orderId) {
			try {
				// make auth
				$this->gocryptoPay->setCredentials($this->client_id, $this->client_secret);
				if ($this->gocryptoPay->auth()) {
					// generate payment
					$order = new WC_Order($orderId);
					$baseUrl = $this->get_return_url($order);

					if (parse_url($baseUrl, PHP_URL_QUERY)) {
						$baseUrl .= '&';
					} else {
						$baseUrl .= '?';
					}

					// set charge data
					$chargeData = array(
						'shop_name' => get_bloginfo('name'),
						'language' => $this->get_lang(),
						'order_number' => $orderId,
						'amount' => round($order->get_total() * 100),
						'discount' => round($order->get_total_discount() * 100),
						'currency_code' => $order->get_currency(),
						'customer_email' => $order->get_billing_email(),
						'callback_endpoint' => $baseUrl . $this->id .'&order_id=' . $orderId
					);
					$this->gocryptoPay->logger->writeLog($chargeData['callback_endpoint']);

					// get all items
					foreach ($order->get_items() as $itemID => $item) {
						$itemData = [
							'name' => $item->get_name(),
							'quantity' => $item->get_quantity(),
							'price' => round($item->get_total() * 100),
							'tax' => round($item->get_subtotal_tax() * 100)
						];

						$chargeData['items'][] = $itemData;
					}

					// generate charge
					$charge = $this->gocryptoPay->generateCharge($chargeData);
					$redirectUrl = $charge['redirect_url'];
					if ($redirectUrl) {
						return array(
							'result' => 'success',
							'redirect' => $redirectUrl,
						);
					}
				}

				return array(
					'result' => 'failure',
					'redirect' => ''
				);
			} catch (Exception $ex) {
				wc_add_notice($ex->getMessage(), 'error');
				exit;
			}
		}

		public function check_response() {
			try {
				// make auth
				$this->gocryptoPay->setCredentials($this->client_id, $this->client_secret);
				if ($this->gocryptoPay->auth()) {
					$orderId = !empty($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : 0;
					if ($orderId == 0 || $orderId == '') {
						$this->gocryptoPay->logger->writeLog('Payment check...wrong order ID!');
						wc_add_notice(__('Payment failed!', 'gocrypto_pay'), 'error');
						wp_redirect(esc_url(wc_get_cart_url()));
						exit;
					}
					$order = wc_get_order($orderId);

					// get params
					$orderKey = !empty($_GET['key']) ? sanitize_text_field($_GET['key']) : null;

					// check order key
					if ($order->get_order_key() != $orderKey) {
						$this->gocryptoPay->logger->writeLog('Payment check...wrong order key!');
						wc_add_notice(__('Payment failed!', 'gocrypto_pay'), 'error');
						wp_redirect(esc_url(wc_get_cart_url()));
						exit;
					}

					// check order status
					if ($order->has_status('completed') || $order->has_status('processing')) {
						wc_add_notice(__('Order is already completed!', 'gocrypto_pay'), 'error');
						wp_redirect(esc_url(wc_get_cart_url()));
						exit;
					}

					// check transaction status
					$transactionId = !empty($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : null;
					$paymentMethod = !empty($_GET['payment_method']) ? sanitize_text_field($_GET['payment_method']) : null;
					if (null != $transactionId) {
						$transactionStatus = $this->gocryptoPay->checkTransactionStatus($transactionId);
						if ($transactionStatus == 'SUCCESS') {
							global $woocommerce;
							/* translators: %s: payment method */
							$statusMsg = sprintf(__('Payment completed with %s.', 'gocrypto_pay'), $paymentMethod);
							$order->update_status('processing', $statusMsg);
							$woocommerce->cart->empty_cart();

							// send email to costumer
							WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
							// send email to admin
							WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
						} else {
							wp_redirect(esc_url_raw($order->get_cancel_order_url_raw()));
							exit;
						}
					} else {
						wp_redirect(esc_url_raw($order->get_cancel_order_url_raw()));
						exit;
					}
				} else {
					$this->gocryptoPay->logger->writeLog('Payment check...auth failed!');
					wc_add_notice(__('Payment failed!', 'gocrypto_pay'), 'error');
					wp_redirect(esc_url(wc_get_cart_url()));
					exit;
				}
			} catch (Exception $ex) {
				$this->gocryptoPay->logger->writeLog('Payment check...payment failed!');
				wc_add_notice(__('Payment failed!', 'gocrypto_pay'), 'error');
				wp_redirect(esc_url(wc_get_cart_url()));
				exit;
			}
		}

		public function get_lang() {
			$language = explode('-', get_bloginfo('language'));
			$language = $language[0];

			if (function_exists('icl_object_id')) {
				global $sitepress;
				// avoids a fatal error with Polylang
				if (isset($sitepress)) {
					$language = strtoupper($sitepress->get_current_language());
					if (strpos($language, 'CS')) {
						$language = 'CZ';
					}
				}
			}

			return strtolower($language);
		}
	}
}

/**
 * Payment class initialization
 */

add_filter('woocommerce_payment_gateways', 'add_goc_pay_class');
function add_goc_pay_class($methods) {
	$methods[] = 'WC_GOC_Pay';
	return $methods;
}

/**
 * Plugin settings link
 */

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'goc_settings_link');
function goc_settings_link($links) {
	$settingsLink = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . GOC_PLUGIN_ID) . '">Settings</a>';
	array_push($links, $settingsLink);
	return $links;
}

/**
 * Check payment initialization
 */

add_action('init', 'init_goc_check_payment');
function init_goc_check_payment() {
	if (isset($_GET[GOC_PLUGIN_ID])) {
		WC()->payment_gateways();

		/**
		 * Check payment initialization
		 *
		 * @since Unknown
		 */
		do_action('goc_check_payment');
	}
}

/**
 * Styles and Scripts initialization
 */

add_action('wp_enqueue_scripts', 'goc_enqueue_scripts_and_styles');
function goc_enqueue_scripts_and_styles() {
	wp_enqueue_style(GOC_PLUGIN_ID, plugins_url('/public/assets/css/pay.css', __FILE__), array(), GOC_PLUGIN_VERSION);
}

/**
 * Deactivate plugin
 */

register_deactivation_hook(__FILE__, 'goc_pay_deactivate');
function goc_pay_deactivate() {
	delete_option('woocommerce_' . GOC_PLUGIN_ID . '_settings');
}
