<?php
/**
 * Plugin Name: Cebelca BIZ
 * Plugin URI:
 * Description: Connects WooCommerce to Cebelca.biz for invoicing and optionally inventory
 * Version: 0.0.112
 * Author: JankoM
 * Author URI: http://refaktorlabs.com
 * Developer: Janko M.
 * Developer URI: http://refaktorlabs.com
 * Text Domain: woocommerce-cebelcabiz
 * Domain Path: /languages
 *
 */

// Status: Stable

if ( ! class_exists( 'WC_Cebelcabiz' ) ) {

    // Constants for plugin configuration
    define("WOOCOMM_INVFOX_DEBUG", true);
    define("WOOCOMM_INVFOX_LOG_FILE", WP_CONTENT_DIR . '/cebelcabiz-debug.log');
    define("WOOCOMM_INVFOX_VERSION", '0.0.112');
    
    /**
     * Debug logging function
     * 
     * @param mixed $x The data to log
     * @param string $y Optional label for the log entry
     * @return void
     */
	function woocomm_invfox__trace( $x, $context = "" ) {
        if (WOOCOMM_INVFOX_DEBUG) {
            // Format the message with consistent structure
            $formatted_x = is_string($x) ? $x : print_r($x, true);
            $context_prefix = $context ? "[$context] " : "";
            $message = "WC_Cebelcabiz: " . $context_prefix . $formatted_x;
            
            // Log to PHP error log
            error_log($message);
            
            // Also log to a dedicated file for easier debugging
            if (defined('WOOCOMM_INVFOX_LOG_FILE')) {
                $timestamp = date('[Y-m-d H:i:s]');
                file_put_contents(
                    WOOCOMM_INVFOX_LOG_FILE, 
                    $timestamp . ' ' . $message . PHP_EOL, 
                    FILE_APPEND
                );
            }
        }
	}
    
	// Load required libraries
	require_once( plugin_dir_path( __FILE__ ) . 'lib/invfoxapi.php' );
	require_once( plugin_dir_path( __FILE__ ) . 'lib/strpcapi.php' );
                                           
    $conf = null; // Will be initialized in the constructor
    
	/**
	 * Main plugin class
	 */
	class WC_Cebelcabiz {

		/**
		 * Plugin configuration
		 * @var array
		 */
		protected $conf = [];

		/**
		 * Default configuration values
		 * @var array
		 */
		protected $defaults = [
			'api_domain' => 'www.cebelca.biz',
			'app_name' => 'Cebelca BIZ',
			'use_shop_id_for_docnum' => false,
			'fiscal_test_mode' => false,
			'vat_levels_list' => '0, 5, 9.5, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27',
			'payment_methods_map' => 'PayPal->PayPal;Gotovina->Gotovina',
			'fiscalize_payment_methods' => '* - vsi',
			'customer_general_payment_period' => '5',
			'proforma_days_valid' => '10',
			'partial_sum_label' => 'Seštevek',
			'order_num_label' => 'Na osnovi naročila:',
			'round_calculated_netprice_to' => 4,
			'round_calculated_taxrate_to' => 1,
			'round_calculated_shipping_taxrate_to' => 2,
		];

		/**
		 * Construct the plugin.
		 */
		public function __construct() {
            // Load configuration
			$this->conf = get_option('woocommerce_cebelcabiz_settings', []);
			
			// Apply defaults for missing configuration values
			foreach ($this->defaults as $key => $value) {
				if (!isset($this->conf[$key])) {
					$this->conf[$key] = $value;
				}
			}
            
			// Initialize hooks
			$this->init_hooks();
		}

		/**
		 * Initialize all WordPress hooks
		 */
		private function init_hooks() {
			// Core plugin initialization
			add_action('plugins_loaded', array($this, 'init'));
			
			// Order status change hooks
			add_action('woocommerce_order_status_on-hold', array($this, '_woocommerce_order_status_on_hold'));
			add_action('woocommerce_order_status_processing', array($this, '_woocommerce_order_status_processing'));
			add_action('woocommerce_order_status_completed', array($this, '_woocommerce_order_status_completed'));

			// REST API endpoint
            add_action('rest_api_init', 'create_custom_endpoint');
            
			// Email attachment hook
			add_filter('woocommerce_email_attachments', array($this, '_attach_invoice_pdf_to_email'), 10, 3);

			// Admin notices
			add_action('admin_notices', array($this, 'admin_notices'));

			// Order actions
			if (!empty($this->conf) && !empty($this->conf['order_actions_enabled'])) {
				$this->init_order_actions();
			}
		}

		/**
		 * Initialize order action hooks
		 */
		private function init_order_actions() {
			add_action('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
			
			// Process custom order actions
			add_action('woocommerce_order_action_cebelcabiz_create_invoice', array(
				$this,
				'process_custom_order_action_invoice'
			));
			add_action('woocommerce_order_action_cebelcabiz_create_proforma', array(
				$this,
				'process_custom_order_action_proforma'
			));
			add_action('woocommerce_order_action_cebelcabiz_create_advance', array(
				$this,
				'process_custom_order_action_advance'
			));
			add_action('woocommerce_order_action_cebelcabiz_create_invt_sale', array(
				$this,
				'process_custom_order_action_invt_sale'
			));
			add_action('woocommerce_order_action_cebelcabiz_check_invt_items', array(
				$this,
				'process_custom_order_action_check_invt_items'
			));
			add_action('woocommerce_order_action_cebelcabiz_mark_invoice_paid', array(
				$this,
				'process_custom_order_action_mark_invoice_paid'
			));
		}


        /**
         * Plugin activation hook callback
         */
        public static function myplugin_activate() {
            // Create invoices directory if it doesn't exist
            $upload = wp_upload_dir();
            $upload_dir = $upload['basedir'] . '/invoices';
            
            if (!is_dir($upload_dir)) {
                $dir_created = wp_mkdir_p($upload_dir);
                if (!$dir_created) {
                    error_log("Cebelca BIZ: Failed to create invoices directory at: " . $upload_dir);
                    return;
                }
                
                // Set proper permissions
                if (!chmod($upload_dir, 0755)) {
                    error_log("Cebelca BIZ: Failed to set permissions on invoices directory: " . $upload_dir);
                }
            }
            
            // Verify directory is writable
            if (!is_writable($upload_dir)) {
                error_log("Cebelca BIZ: Invoices directory is not writable: " . $upload_dir);
            }
            
            // Create log file if debug is enabled
            if (WOOCOMM_INVFOX_DEBUG && defined('WOOCOMM_INVFOX_LOG_FILE')) {
                $log_dir = dirname(WOOCOMM_INVFOX_LOG_FILE);
                if (!is_dir($log_dir)) {
                    $log_dir_created = wp_mkdir_p($log_dir);
                    if (!$log_dir_created) {
                        error_log("Cebelca BIZ: Failed to create log directory at: " . $log_dir);
                        return;
                    }
                }
                
                if (!file_exists(WOOCOMM_INVFOX_LOG_FILE)) {
                    $log_written = file_put_contents(
                        WOOCOMM_INVFOX_LOG_FILE, 
                        date('[Y-m-d H:i:s]') . ' Cebelca BIZ plugin activated' . PHP_EOL
                    );
                    
                    if ($log_written === false) {
                        error_log("Cebelca BIZ: Failed to write to log file: " . WOOCOMM_INVFOX_LOG_FILE);
                    }
                }
            }
        }
 
		/**
		 * Initialize the plugin.
		 */
		public function init() {
			// Check if WooCommerce is installed
			if (!class_exists('WC_Integration')) {
				add_action('admin_notices', function() {
					echo '<div class="error"><p>' . 
						__('Cebelca BIZ requires WooCommerce to be installed and active.', 'woocommerce-cebelcabiz') . 
						'</p></div>';
				});
				return;
			}

			// Include our integration class
			include_once plugin_dir_path(__FILE__) . 'includes/class-wc-integration-cebelcabiz.php';

			// Register the integration
			add_filter('woocommerce_integrations', array($this, 'add_integration'));
		}

		/**
		 * Show admin notices from stack
		 */
		public function admin_notices() {
			// Check if user has permission to view admin notices
			if (!current_user_can('manage_options')) {
				return;
			}
			
			$notices = get_option('cebelcabiz_deferred_admin_notices');
			
			if (!empty($notices) && is_array($notices)) {
				foreach ($notices as $notice) {
					// Use wp_kses with stricter allowed HTML tags for better XSS protection
					$allowed_html = array(
						'p' => array(),
						'strong' => array(),
						'em' => array(),
						'br' => array(),
					);
					
					$notice = wp_kses($notice, $allowed_html);
					
					if (strpos($notice, "NAPAKA") === false) {
						echo "<div class='notice notice-success is-dismissible'><p>{$notice}</p></div>";
					} else {
						echo "<div class='notice notice-error is-dismissible'><p>{$notice}</p></div>";
					}
				}
				delete_option('cebelcabiz_deferred_admin_notices');
			}
		}

		/**
		 * Add our items for order actions box
		 * 
		 * @param array $actions Existing actions
		 * @return array Modified actions
		 */
		public function add_order_meta_box_actions($actions) {
			$app_name = esc_html($this->conf['app_name']);
			
			$actions['cebelcabiz_create_invoice'] = sprintf(__('%s: Make invoice', 'woocom-invfox'), $app_name);
			$actions['cebelcabiz_mark_invoice_paid'] = sprintf(__('%s: Mark invoice paid', 'woocom-invfox'), $app_name);
			$actions['cebelcabiz_create_proforma'] = sprintf(__('%s: Make proforma', 'woocom-invfox'), $app_name);
			$actions['cebelcabiz_create_advance'] = sprintf(__('%s: Make advance', 'woocom-invfox'), $app_name);
			$actions['cebelcabiz_check_invt_items'] = sprintf(__('%s: Check inventory', 'woocom-invfox'), $app_name);
			$actions['cebelcabiz_create_invt_sale'] = sprintf(__('%s: Make invent. sale', 'woocom-invfox'), $app_name);

			return $actions;
		}

		/**
		 * Add a new integration to WooCommerce.
		 * 
		 * @param array $integrations Existing integrations
		 * @return array Modified integrations
		 */
		public function add_integration($integrations) {
			$integrations[] = 'WC_Integration_Cebelcabiz';
			return $integrations;
		}

		/**
		 * Process custom order action to create invoice draft
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_invoice($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				$this->_make_document_in_invoicefox($order, "invoice_draft");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_invoice");
			}
		}

		/**
		 * Process custom order action to create proforma
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_proforma($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				$this->_make_document_in_invoicefox($order, "proforma");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_proforma");
			}
		}
		
		/**
		 * Process custom order action to create advance draft
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_advance($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				$this->_make_document_in_invoicefox($order, "advance_draft");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_advance");
			}
		}

		/**
		 * Process custom order action to create inventory sale
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_invt_sale($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				$this->_make_document_in_invoicefox($order, "inventory");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_invt_sale");
			}
		}

		/**
		 * Process custom order action to create complete invoice
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_full_invoice($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				$this->_make_document_in_invoicefox($order, "invoice_complete");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_full_invoice");
			}
		}

		/**
		 * Process invoice download
		 * 
		 * @param WC_Order $order Order object
		 * @return string|bool Path to downloaded file or false on failure
		 */
		public function process_invoice_download($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return false;
			}
			
			try {
				return $this->_woocommerce_order_invoice_pdf($order, "invoice-sent");
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_invoice_download");
				return false;
			}
		}

		/**
		 * Process custom order action to mark invoice as paid
		 * 
		 * @param WC_Order $order Order object
		 */
		public function process_custom_order_action_mark_invoice_paid($order) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			try {
				if (empty($this->conf['api_key'])) {
					throw new Exception("API key is not configured");
				}
				
				$api = new InvfoxAPI($this->conf['api_key'], $this->conf['api_domain'], false);
				$api->setDebugHook("woocomm_invfox__trace");
				
				$currentPM = sanitize_text_field($order->get_payment_method_title());
				woocomm_invfox__trace("Processing payment method: " . $currentPM, "Payment");
				woocomm_invfox__trace("Payment methods map: " . $this->conf['payment_methods_map'], "Payment");
				
				$payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
				woocomm_invfox__trace("Mapped payment method: " . $payment_method, "Payment");

				if ($payment_method == -2) {
					throw new Exception("Način plačila $currentPM manjka v nastavitvah pretvorbe.");
				} else if ($payment_method == -1) {
					throw new Exception("Napačna oblika nastavitve: Pretvorba načinov plačila.");
				}
				
				woocomm_invfox__trace("Marking invoice as paid for order #" . $order->get_id(), "Payment");
				$res = $api->markInvoicePaid($order->get_id(), $payment_method);
				woocomm_invfox__trace("API response: " . print_r($res, true), "Payment");
				
				// Check if the API call was successful
				if (!$res || (is_object($res) && method_exists($res, 'isOk') && !$res->isOk())) {
					woocomm_invfox__trace("Failed to mark invoice as paid: " . print_r($res, true), "API Error");
					throw new Exception("Ni bilo mogoče označiti računa kot plačanega. Preverite povezavo s strežnikom Čebelca BIZ.");
				}
				
				$this->add_admin_notice("Plačilo zabeleženo");
				
			} catch (Exception $e) {
				$this->add_admin_notice("NAPAKA: " . esc_html($e->getMessage()));
				woocomm_invfox__trace($e->getMessage(), "Error in process_custom_order_action_mark_invoice_paid");
			}
		}

		function process_custom_order_action_check_invt_items( $order ) {
			// Check if user has permission to manage WooCommerce
			if (!current_user_can('manage_woocommerce')) {
				woocomm_invfox__trace("Permission denied: User cannot manage WooCommerce", "Security");
				return;
			}
			
			$items = array();

			foreach ( $order->get_items() as $item ) {
				if ( 'line_item' == $item['type'] ) {
					$product = $item->get_product(); // $order->get_product_from_item( $item );
					$items[] = array(
						'code' => sanitize_text_field($product->get_sku()),
						'qty'  => intval($item['qty'])
					);
				}
			}

			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], false );
			$api->setDebugHook( "woocomm_invfox__trace" );

			$resp = $api->checkInvtItems( $items, $this->conf['from_warehouse_id'], $api->_toSIDate( date( 'd.m.Y' ) ) );
			
			// Check if the API call was successful
			if (!$resp) {
				woocomm_invfox__trace("Failed to check inventory items", "API Error");
				$notices = get_option('cebelcabiz_deferred_admin_notices', array());
				$notices[] = "NAPAKA: Ni bilo mogoče preveriti zalog. Preverite povezavo s strežnikom Čebelca BIZ.";
				update_option('cebelcabiz_deferred_admin_notices', $notices);
				return;
			}
			
			$msg  = "";

			if ( $resp ) {
				$first = true;
				foreach ( $resp as $item ) {
					$code = sanitize_text_field($item['code']);
					$result = isset($item['result']) ? $item['result'] : null;
					$result_text = $result === null ? "item code not found" : ($result >= 0 ? "OK, on inventory (+ {$result})" : "less inventory ({$result})");
					$msg .= ($first ? "" : ", ") . $code . ": " . $result_text;
					$first = false;
				}
			}

			$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
			$notices[] = "Inventory items checked: " . $msg;

			update_option( 'cebelcabiz_deferred_admin_notices', $notices );
		}
        
		function _woocommerce_order_status_on_hold( $order ) {
            // if ( $this->conf['on_order_on_hold'] == "create_proforma" ) 
            if ( strpos($this->conf['on_order_on_hold'], "create_proforma") !== false) {
                $this->_make_document_in_invoicefox( $order, "proforma" );
			}
			if ( $this->conf['on_order_on_hold'] == "create_invoice_draft" ) {
				$this->_make_document_in_invoicefox( $order, 'invoice_draft' );
			}
		}
        
		function _woocommerce_order_status_processing( $order ) {
            if ( strpos($this->conf['on_order_processing'], "create_proforma") !== false) {
                $this->_make_document_in_invoicefox( $order, "proforma" );
			}
			if ( $this->conf['on_order_processing'] == "create_invoice_draft" ) {
				$this->_make_document_in_invoicefox( $order, 'invoice_draft' );
			}
		}


		function _woocommerce_order_status_completed( $order ) {

            if ( strpos($this->conf['on_order_completed'], "create_proforma") !== false) {
                $this->_make_document_in_invoicefox( $order, "proforma" );
			}

            if ( $this->conf['on_order_completed'] == "create_invoice_draft" ) {
				$this->_make_document_in_invoicefox( $order, 'invoice_draft' );
			} else if ( strpos($this->conf['on_order_completed'], "create_invoice_complete") !== false) {
              
              woocomm_invfox__trace("Processing order completion settings", "Config");
              woocomm_invfox__trace("Order completion setting: " . $this->conf['on_order_completed'], "Config");
              woocomm_invfox__trace("Include payment: " . (strpos($this->conf['on_order_completed'], "_paid_") !== false ? "Yes" : "No"), "Config");
              woocomm_invfox__trace("Include inventory: " . (strpos($this->conf['on_order_completed'], "_inventory_") !== false ? "Yes" : "No"), "Config");

              
              $this->_make_document_in_invoicefox( $order, 
                                                   'invoice_complete',
                                                   strpos($this->conf['on_order_completed'], "_paid_") !== false, 
                                                   strpos($this->conf['on_order_completed'], "_inventory_") !== false );
			}
		}

		/**
		 * function that collects data and calls invfoxapi functions to create document
		 */
		function _make_document_in_invoicefox( $order_id, $document_to_make = "", $markPaid = 0, $decreaseInventory = 0 ) {

            $vatLevels = parseVatLevels($this->conf['vat_levels_list']);
            
			$order = new WC_Order( $order_id );

			if ( $order->get_total() < 0.001 ) {
				return true;	
			}
			
			if ( $document_to_make ) {
				$this->conf['document_to_make'] = $document_to_make;
			}

			woocomm_invfox__trace("Creating document: " . $this->conf['document_to_make'], "Document");
			woocomm_invfox__trace("Payment method: " . $order->get_payment_method(), "Document");
			woocomm_invfox__trace("Payment method title: " . $order->get_payment_method_title(), "Document");

			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
//			$api->setDebugHook( "woocomm_invfox__trace" );

			$vatNum = get_post_meta( $order->get_id(), 'VAT Number', true );
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), 'vat_number', true ); }
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), '_vat_number', true ); }
            
            // Sanitize VAT number
            if (!empty($vatNum)) {
                $vatNum = sanitize_text_field($vatNum);
                // Additional validation for VAT number format
                if (!preg_match('/^[A-Z0-9]{2,15}$/', $vatNum)) {
                    woocomm_invfox__trace("Invalid VAT number format: " . $vatNum, "Customer Validation Warning");
                }
            }

			// Validate required customer data
			$firstName = sanitize_text_field($order->get_billing_first_name());
			$lastName = sanitize_text_field($order->get_billing_last_name());
			$company = sanitize_text_field($order->get_billing_company());
			
			// Check if we have at least a name (first name, last name, or company)
			if (empty($firstName) && empty($lastName) && empty($company)) {
				woocomm_invfox__trace("Missing required customer name data", "Customer Validation Error");
				$notices = get_option('cebelcabiz_deferred_admin_notices', array());
				$notices[] = "NAPAKA: Manjkajo podatki o stranki. Vsaj ime, priimek ali podjetje je obvezno.";
				update_option('cebelcabiz_deferred_admin_notices', $notices);
				return false;
			}
			
			// Prepare customer name with available data
			$customerName = trim($firstName . " " . $lastName);
			if (!empty($company)) {
				$customerName = !empty($customerName) ? $customerName . ", " . $company : $company;
			}
			
			// If still empty (shouldn't happen due to validation above), use a placeholder
			if (empty($customerName)) {
				$customerName = "Stranka #" . $order->get_id();
				woocomm_invfox__trace("Using placeholder customer name: " . $customerName, "Customer Data");
			}
			
			woocomm_invfox__trace("Customer name: " . $customerName, "Customer Data");
			
			// Sanitize all customer data to prevent XSS
			$address1 = sanitize_text_field($order->get_billing_address_1());
			$address2 = sanitize_text_field($order->get_billing_address_2());
			$postcode = sanitize_text_field($order->get_billing_postcode());
			$city = sanitize_text_field($order->get_billing_city());
			$country = sanitize_text_field($order->get_billing_country());
			$phone = sanitize_text_field($order->get_billing_phone());
			$email = sanitize_email($order->get_billing_email());
			
			$r = $api->assurePartner( array(
				'name'           => $customerName,
				'street'         => $address1 . "\n" . $address2,
				'postal'         => $postcode,
				'city'           => $city,
				'country'        => $country,
				'vatid'          => $vatNum,
				'phone'          => $phone,
				'website'        => "",
				'email'
