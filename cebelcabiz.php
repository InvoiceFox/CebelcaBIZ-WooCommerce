<?php
/**
 * Plugin Name: Cebelca BIZ
 * Plugin URI:
 * Description: Connects WooCommerce to Cebelca.biz for invoicing and optionally inventory
 * Version: 0.8.4
 * Author: JankoM
 * Author URI: https://github.com/refaktor/
 * Developer: Janko M.
 * Developer URI: https://github.com/refaktor/
 * Text Domain: woocommerce-cebelcabiz
 * Domain Path: /languages
 *
 */

// Status: Stable

if ( ! class_exists( 'WC_Cebelcabiz' ) ) {

    // Constants for plugin configuration
    define("WOOCOMM_INVFOX_LOG_FILE", WP_CONTENT_DIR . '/cebelcabiz-debug.log');
    define("WOOCOMM_INVFOX_VERSION", '0.8.0');
    
    // Debug mode will be set based on settings
    $debug_enabled = false;
    $settings = get_option('woocommerce_cebelcabiz_settings');
    if (isset($settings['debug_mode']) && $settings['debug_mode'] === 'yes') {
        $debug_enabled = true;
    }
    define("WOOCOMM_INVFOX_DEBUG", $debug_enabled);
    
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
    require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-cebelcabiz-log-viewer.php' );
    
    // Initialize log viewer
    $log_viewer = new WC_Cebelcabiz_Log_Viewer();
                                           
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
            
            // Enqueue admin scripts and styles for tabs
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

			// Order actions
			if (!empty($this->conf) && !empty($this->conf['order_actions_enabled'])) {
				$this->init_order_actions();
			}
		}

        /**
         * Enqueue admin scripts and styles
         */
        public function enqueue_admin_scripts() {
            // This method is kept for future use if needed
            // Currently not enqueueing any scripts or styles
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				$this->add_admin_notice("NAPAKA: " . $e->getMessage());
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
				
				$payment_method = $this->mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
				woocomm_invfox__trace("Mapped payment method: " . $payment_method, "Payment");

				if ($payment_method == -2) {
					throw new Exception("Način plačila \"$currentPM\" manjka v nastavitvah pretvorbe. Plačilo v Čebelci ni bilo zabeleženo.");
				} else if ($payment_method == -1) {
					throw new Exception("Napačna oblika nastavitve: Pretvorba načinov plačila. Plačilo v Čebelci ni bilo zabeleženo.");
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

			// Try WooCommerce order meta first (modern method, compatible with HPOS)
			$vatNum = $order->get_meta( 'vat_number' );
			if (!$vatNum) { $vatNum = $order->get_meta( 'VAT Number' ); }
			if (!$vatNum) { $vatNum = $order->get_meta( '_vat_number' ); }
			// Fallback to WordPress post meta (legacy method)
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), 'vat_number', true ); }
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), 'VAT Number', true ); }
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), '_vat_number', true ); }
            
            // Sanitize VAT number
            if (!empty($vatNum)) {
                $vatNum = sanitize_text_field($vatNum);
                // More flexible VAT number validation that accepts various international formats
                // Allows uppercase and lowercase letters, numbers, and common separators
                if (!preg_match('/^[A-Za-z0-9\.\-\s]{2,20}$/', $vatNum)) {
                    woocomm_invfox__trace("Unusual VAT number format: " . $vatNum, "Customer Validation Notice");
                    // This is just a warning, not an error that blocks processing
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
				'email'          => $email,
				'notes'          => '',
				'vatbound'       => ! ! $vatNum,
				'custaddr'       => '',
				'payment_period' => $this->conf['customer_general_payment_period'],
				'street2'        => ''
			) );

			if ( !$r->isOk() ) {
				woocomm_invfox__trace("Failed to assure partner: " . print_r($r->getErr(), true), "API Error");
				$notices = get_option('cebelcabiz_deferred_admin_notices', array());
				$notices[] = "NAPAKA: Ni bilo mogoče ustvariti ali posodobiti stranke v Čebelci BIZ. Preverite podatke o stranki.";
				update_option('cebelcabiz_deferred_admin_notices', $notices);
				return false;
			}
			
			// Partner was successfully created or updated

			$vatLevelsOK = true;
			
			woocomm_invfox__trace("Partner assured, proceeding with document creation", "Document");

			$clientIdA = $r->getData();
			$clientId  = $clientIdA[0]['id'];
			$date1     = $api->_toSIDate( date( 'Y-m-d' ) ); //// TODO LONGTERM ... figure out what we do with different Dates on api side (maybe date optionally accepts dbdate format)
			$invid     = $this->conf['use_shop_id_for_docnum'] ? str_pad( $order->get_id(), 5, "0", STR_PAD_LEFT ) : "";
			$body2     = array();

			foreach ( $order->get_items() as $item ) {
				if ( 'line_item' == $item['type'] ) {
					$product        = $item->get_product(); // $order->get_product_from_item( $item );
					woocomm_invfox__trace("Processing product: " . $product->get_name() . " (SKU: " . $product->get_sku() . ")", "Product");

					$mu_ = $product->get_meta( 'unit_of_measurement', true );
					$mu = $mu_ ? $mu_ : "";

					$price = wc_get_price_excluding_tax( $product );
					//$price = $product->get_price_excluding_tax();
					$quantity = $item->get_quantity();
					$subtotal = $item->get_total(); //  + $item->get_subtotal_tax();
					if ($quantity != 0) { 
						$discounted_price = $subtotal / $quantity;
					} else {
						$discounted_price = 0;
					}
					if ($price != 0) {
						$discount_percentage = round(100 - ( $discounted_price / $price ) * 100, 2);
					} else {
						$discount_percentage = 0;
					}
					woocomm_invfox__trace("Price: $price, Quantity: $quantity, Subtotal: $subtotal", "Product");
					woocomm_invfox__trace("Discounted price: $discounted_price, Discount percentage: $discount_percentage%", "Product");
				
					$attributes_str = woocomm_invfox_get_item_attributes( $item );
					$variation_str  = woocomm_invfox_get_order_item_variations( $item );
					$vatLevel = calculatePreciseSloVAT($item['line_total'], $item['line_tax'], $vatLevels);
					if ($vatLevel < 0) {
						$vatLevelsOK = false;
					}
					$body2[]        = array(
						'code'     => $product->get_sku(),
						'title'    => 
						($this->conf['add_sku_to_line'] == "yes" && $this->conf['document_to_make'] != 'inventory' ? $product->get_sku(). ": " : "" ).
						$product->get_title() .
						( $attributes_str ? "\n" . $attributes_str : "" ) . // ( $this->conf['add_post_content_in_item_descr'] == "yes" ? "\n" . $product->get_content : "" ),
						( $variation_str ? "\n" . $variation_str : "" ), // ( $this->conf['add_post_content_in_item_descr'] == "yes" ? "\n" . $product->get_content : "" ),q
						'qty'      => $quantity,
						'mu'       => $mu,
						'price'    => $price, // round( $item['line_total'] / $item['qty'], $this->conf['round_calculated_netprice_to'] ),
						'vat'      => $vatLevel,
						'discount' => $discount_percentage
					);
				}
			}
			
			// SHIPPING
			if ( $this->conf['document_to_make'] != 'inventory' && $order->get_shipping_total() > 0 ) {
				woocomm_invfox__trace("Adding shipping to document", "Document");

				if ( $this->conf['partial_sum_label'] ) {
					$body2[] = array(
						'title'    => "= " . $this->conf['partial_sum_label'],
						'qty'      => 1,
						'mu'       => '',
						'price'    => 0,
						'vat'      => 0,
						'discount' => 0
					);
				}

				// Calculate precise net shipping from gross amount to avoid rounding issues
				// WooCommerce rounds net shipping to 2 decimals, but we need 4 decimals for accurate invoicing
				// Use rounded values (2 decimals) for gross calculation - this is what customer actually pays
				$shipping_total = round((float) $order->get_shipping_total(), 2);
				$shipping_tax = round((float) $order->get_shipping_tax(), 2);
				$gross_shipping = round($shipping_total + $shipping_tax, 2);
				
				// Get the precise VAT rate from the function that can handle small inaccuracies
				$shipping_vat_rate = calculatePreciseSloVAT($shipping_total, $shipping_tax, $vatLevels);
				
				// Calculate precise net price from the rounded gross using the correct VAT rate
				if ($gross_shipping > 0 && $shipping_vat_rate > 0) {
					$precise_net_shipping = $gross_shipping / (1 + ($shipping_vat_rate / 100));
				} else {
					$precise_net_shipping = $shipping_total;
				}
				
				woocomm_invfox__trace("Shipping - Rounded Net: $shipping_total, Rounded Tax: $shipping_tax, Gross: $gross_shipping, VAT Rate: $shipping_vat_rate%, Precise Net: $precise_net_shipping", "Document");

				$body2[] = array(
					'title'    => "Dostava - " . $order->get_shipping_method(),
					'qty'      => 1,
					'mu'       => '',
					'price'    => round($precise_net_shipping, 4),
					'vat'      => $shipping_vat_rate,
					'discount' => 0
				);
			}
			
			// FEES
			foreach( $order->get_items('fee') as $item_id => $item_fee ){
				$fee_total = $item_fee->get_total();
				$fee_total_tax = $item_fee->get_total_tax();
				$vatLevel = calculatePreciseSloVAT($fee_total, $fee_total_tax, $vatLevels);
				if ($vatLevel < 0) {
					$vatLevelsOK = false;
				}
				$body2[] = array(
					'title'    => $item_fee->get_name(),
					'qty'      => 1,
					'mu'       => '',
					'price'    => $fee_total,
					'vat'      => $vatLevel,
					'discount' => 0
				);
			}
			
			if ( $this->conf['document_to_make'] == 'invoice_draft' || $this->conf['document_to_make'] == 'advance_draft' ) {
				woocomm_invfox__trace("Creating invoice draft", "Document");

				$r2 = $api->createInvoice( array(
					'title'           => $invid,
					'date_sent'       => $date1,
					'date_to_pay'     => $date1,
					'date_served'     => $date1, // MAY NOT BE NULL IN SOME VERSIONS OF USER DBS
					'id_partner'      => $clientId,
					'taxnum'          => '-',
					'doctype'         => $this->conf['document_to_make'] == 'advance_draft' ? 1 : 0,
					'id_document_ext' => $order->get_id(),
					'pub_notes'       => $this->conf['order_num_label'] . ' #' . $order->get_order_number() 
					// , // uncomment these 3 lines if invoices are always already paid when created and you want payment method to display on PDF instead of date_to_pay
					// 'payment_act'     => "1",
					// 'payment'         => "paid"
				), $body2 );

				if ( $r2->isOk() ) {
					$invA = $r2->getData();
					$docnum = $invA[0]['title'] ? $invA[0]['title'] : "OSNUTEK";
					$order->add_order_note( "Račun {$docnum} je bil ustvarjen na {$this->conf['app_name']}." );
				}

			} elseif ( $this->conf['document_to_make'] == 'invoice_complete' ) {
				$r2 = $api->createInvoice( array(
					'title'           => $invid,
					'date_sent'       => $date1,
					'date_to_pay'     => $date1,
					'date_served'     => $date1, // MAY NOT BE NULL IN SOME VERSIONS OF USER DBS
					'id_partner'      => $clientId,
					'taxnum'          => '-',
					'doctype'         => 0,
					'id_document_ext' => $order->get_id(),
					'pub_notes'       => $this->conf['order_num_label'] . ' #' . $order->get_order_number()
					// , // uncomment these 3 lines if invoices are always already paid when created and you want payment method to display on PDF instead of date_to_pay
					// 'payment_act'     => "1",
					// 'payment'         => "paid"
				), $body2 );

				$r3 = Array(Array( "new_title" => "OSNUTEK" ));
				
				if ( $r2->isOk() ) {
					
					woocomm_invfox__trace("Invoice created successfully", "Document");
						
					if ( $vatLevelsOK ) {
						woocomm_invfox__trace("VAT levels validated successfully", "Document");
						
						woocomm_invfox__trace("Preparing to finalize invoice", "Document");
						woocomm_invfox__trace("Fiscal mode: " . $this->conf['fiscal_mode'], "Document");
						$invA = $r2->getData();
						woocomm_invfox__trace("Payment method: " . $order->get_payment_method_title(), "Document");
						woocomm_invfox__trace("Fiscalize payment methods: " . $this->conf['fiscalize_payment_methods'], "Document");
						if ( $this->conf['fiscal_mode'] == "yes" &&
                             isPaymentAmongst($order->get_payment_method_title(), $this->conf['fiscalize_payment_methods'])) {                            
							woocomm_invfox__trace("Using fiscal mode for invoice", "Document");
							woocomm_invfox__trace("Configuration: " . print_r($this->conf, true), "Document");
							if ( $this->conf['fiscal_id_location'] && 
                                 $this->conf['fiscal_op_tax_id'] && 
                                 $this->conf['fiscal_op_name']
							) {
								$r3 = $api->finalizeInvoice( array(
									'id'          => $invA[0]['id'],
									'id_location' => $this->conf['fiscal_id_location'],
									'fiscalize'   => 1,
									'op-tax-id'   => $this->conf['fiscal_op_tax_id'],
									'op-name'     => $this->conf['fiscal_op_name'],
									'test_mode'   => $this->conf['fiscal_test_mode'] == "yes" ? 1 : 0
								) );
							} else {
								woocomm_invfox__trace("Error: Missing required fiscal parameters (ID_LOCATION, OP_TAX_ID, OP_NAME)", "Document");
								
							}
						} else {
							woocomm_invfox__trace("Using non-fiscal mode for invoice", "Document");
							$r3 = $api->finalizeInvoiceNonFiscal( array(
								'id'      => $invA[0]['id'],
								'title'   => "",
								'doctype' => 0
							) );
						}
						
					} else {
						$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
						$notices[] = "NAPAKA: Izračunana davčna stopnja je imela vrednost -1, zato račun ni bil izdan. Preverite 'možne davčne stopnje' na nastavitvah dodatka.";
						update_option( 'cebelcabiz_deferred_admin_notices', $notices );
					}
					
					woocomm_invfox__trace("Checking if invoice should be marked as paid", "Payment");

					if ($markPaid) {
						woocomm_invfox__trace("Preparing to mark invoice as paid", "Payment");

						//                          			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
						//			$api->setDebugHook( "woocomm_invfox__trace" );


						$currentPM = $order->get_payment_method_title();
						woocomm_invfox__trace("Marking invoice as paid", "Payment");
						woocomm_invfox__trace("Current payment method: " . $currentPM, "Payment");
						woocomm_invfox__trace("Payment methods map: " . $this->conf['payment_methods_map'], "Payment");
						$payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
						woocomm_invfox__trace("Mapped payment method: " . $payment_method, "Payment");
						
						$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
						$payment_recorded = false;
						
						if($payment_method == -2) {
							$notices[] = "NAPAKA: Način plačila \"$currentPM\" manjka v nastavitvah pretvorbe. Plačilo v Čebelci ni bilo zabeleženo.";
							$payment_method = "-";
						}
						else if($payment_method == -1) {
							$notices[] = "NAPAKA: Napačna oblika nastavitve: Pretvorba načinov plačila. Plačilo v Čebelci ni bilo zabeleženo.";
							$payment_method = "-";
						}
						else {
							woocomm_invfox__trace("Calling markInvoicePaid API", "Payment");
							$res = $api->markInvoicePaid( $order->get_id(), $payment_method );
							woocomm_invfox__trace("API response: " . print_r($res, true), "Payment");
							
							// Check if the API call was successful
							if ($res && (!is_object($res) || !method_exists($res, 'isOk') || $res->isOk())) {
								$notices[] = "Plačilo zabeleženo";
								$payment_recorded = true;
							} else {
								$notices[] = "NAPAKA: Plačila ni bilo mogoče zabeležiti. Preverite povezavo s strežnikom Čebelca BIZ.";
							}
						}
						
						update_option( 'cebelcabiz_deferred_admin_notices', $notices );
						
						/*
						
                          $currentPM = $order->get_payment_method_title();
                          $payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);

                          $notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
                          if($payment_method == -2) {
                          $notices[] = "Način plačila $currentPM manjak v nastavitvah pretvorbe. Plačilo v Čebelci ni bilo zabeleženo.";
                          }
                          else if($payment_method == -1) {
                          $notices[] = "Napačna oblika nastavitve: Pretvorba načinov plačila. Plačilo v Čebelci ni bilo zabeleženo.";
                          }
                          else {
                          $api->markInvoicePaid( $order->get_id(), $payment_method );
                          // TODO -- poglej kaj je vrnila Čebelca in zapiši v notices
                          $notices[] = "Plačilo zabeleženo";
                          }
                          update_option( 'cebelcabiz_deferred_admin_notices', $notices );
						*/
					}
					
					woocomm_invfox__trace("Checking if inventory should be decreased", "Inventory");
					
					if ($decreaseInventory) {
						woocomm_invfox__trace("Preparing to create inventory document", "Inventory");
						$api->makeInventoryDocOutOfInvoice($invA[0]['id'], $this->conf['from_warehouse_id'], $clientId);
						woocomm_invfox__trace("Inventory document created successfully", "Inventory");
						$notices = get_option('cebelcabiz_deferred_admin_notices', array());
						$notices[] = "Inventory doc created";
						update_option( 'cebelcabiz_deferred_admin_notices', $notices );
					}

					if ( $vatLevelsOK ) { 
						$uploads = wp_upload_dir();
						$upload_path    = $uploads['basedir'] . "/invoices";
						
						//$filename = $api->downloadInvoicePDF( $order->id, $path );
						$filename = $api->downloadPDF( 0, $order->get_id(), $upload_path, 'invoice-sent', '' );
						
						if ($filename === false) {
							$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
							$notices[] = "NAPAKA: Ni bilo mogoče prenesti PDF računa. Preverite povezavo s strežnikom Čebelca BIZ.";
							update_option( 'cebelcabiz_deferred_admin_notices', $notices );
							woocomm_invfox__trace("Failed to download invoice PDF", "PDF Error");
						} else {
							/*
                              $filetype = wp_check_filetype( basename( $filename ), null );
								
                              // Prepare an array of post data for the attachment.
                              $attachment = array(
                              'guid'           => $uploads['url'] . '/' . basename( $filename ),
                              'post_mime_type' => $filetype['type'],
                              'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
                              'post_content'   => '',
                              'post_status'    => 'inherit'
                              );
							
                              // Insert the attachment.
                              $attach_id = wp_insert_attachment( $attachment, $filename, $order->id );
							
                              // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
                              require_once( ABSPATH . 'wp-admin/includes/image.php' );
							
                              // Generate the metadata for the attachment, and update the database record.
                              $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
                              wp_update_attachment_metadata( $attach_id, $attach_data );
							
                              set_post_thumbnail( $order->id, $attach_id );
							*/
							
							add_post_meta( $order->get_id(), 'invoicefox_attached_pdf', $filename );
							woocomm_invfox__trace("Invoice PDF downloaded successfully: " . $filename, "PDF");
						}
					} else {
						$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
						$notices[] = "NAPAKA: Ker račun zaradi neprave DDV stopnje ni bil izdan se tudi ni pripravil PDF za pošiljanje po e-pošti stranki. Popravite DDV stopnje in pošljite ta račun sami.";
						update_option( 'cebelcabiz_deferred_admin_notices', $notices );
					}
					$order->save();
					$docnum = $r3[0]['new_title'] ? $r3[0]['new_title'] : "OSNUTEK";
					$order->add_order_note( "Račun {$docnum} je bil ustvarjen na {$this->conf['app_name']}." );
				}
				
			} elseif ( $this->conf['document_to_make'] == 'proforma' ) {
				woocomm_invfox__trace("Creating proforma invoice", "Document");

				$r2 = $api->createProFormaInvoice( array(
					'title'      => "",
					'date_sent'  => $date1,
					'days_valid' => $this->conf['proforma_days_valid'],
					'id_partner' => $clientId,
					'taxnum'     => '-',
					'id_document_ext' => $order->get_id(),
					'pub_notes'       => $this->conf['order_num_label'] . ' #' . $order->get_order_number()

				), $body2 );

				if ( $r2->isOk() ) {

					woocomm_invfox__trace("Uploading invoice PDF", "Document");

					$uploads     = wp_upload_dir();
					$upload_path    = $uploads['basedir'] . "/invoices";
					$filename = $api->downloadPDF( 0, $order->get_id(), $upload_path, 'preinvoice', '' );
					
					if ($filename === false) {
						$notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
						$notices[] = "NAPAKA: Ni bilo mogoče prenesti PDF predračuna. Preverite povezavo s strežnikom Čebelca BIZ.";
						update_option( 'cebelcabiz_deferred_admin_notices', $notices );
						woocomm_invfox__trace("Failed to download proforma PDF", "PDF Error");
					} else {
						add_post_meta( $order->get_id(), 'invoicefox_attached_pdf', $filename );
						woocomm_invfox__trace("Proforma PDF downloaded successfully: " . $filename, "PDF");
					}
					$order->save();
					woocomm_invfox__trace($filename );
					woocomm_invfox__trace("Invoice PDF upload completed", "Document");

					$invA = $r2->getData();
					$docnum = $invA[0]['title'] ? $invA[0]['title'] : "OSNUTEK";
					$order->add_order_note( "Predračun {$docnum} je bil ustvarjen na {$this->conf['app_name']}." );
					//						$order->add_order_note( "ProForma Invoice No. {$invA[0]['title']} was created at {$this->conf['app_name']}." );
				}

			} elseif ( $this->conf['document_to_make'] == 'inventory' ) {
				woocomm_invfox__trace("Creating inventory sale document", "Document");

				$r2 = $api->createInventorySale( array(
					'title'           => $invid,
					'date_created'    => $date1,
					'id_contact_to'   => $clientId,
					'id_contact_from' => $this->conf['from_warehouse_id'],
					'taxnum'          => '-',
					'doctype'         => 1,
					'pub_notes'       => $this->conf['order_num_label'] . ' #' . $order->get_id()
				), $body2 );

				if ( $r2->isOk() ) {
					$invA = $r2->getData();
					$docnum = $invA[0]['title'] ? $invA[0]['title'] : "OSNUTEK";
					$order->add_order_note( "Skladiščni dok. {$docnum} je bil ustvarjen na {$this->conf['app_name']}." );
				}

			}

			// woocomm_invfox__trace( $r2 );
			woocomm_invfox__trace("Document creation completed", "Document");

			return true;
		}

		/**
		 * Add a notice to the admin notices queue
		 * 
		 * @param string $notice Notice text
		 */
		private function add_admin_notice($notice) {
			$notices = get_option('cebelcabiz_deferred_admin_notices', array());
			$notices[] = $notice;
			update_option('cebelcabiz_deferred_admin_notices', $notices);
		}
		
		/**
		 * Download and retrieve invoice PDF for an order
		 * 
		 * @param WC_Order $order Order object
		 * @param string $resource Resource type (e.g., 'invoice-sent', 'preinvoice')
		 * @param string $meta_key Optional meta key to store the PDF path
		 * @return string|bool Path to downloaded file or false on failure
		 */
		function _woocommerce_order_invoice_pdf($order, $resource, $meta_key = 'invoicefox_attached_pdf') {
			// If we don't have the PDF or the file doesn't exist, download it
			woocomm_invfox__trace("No existing PDF found for " . $resource . ", downloading new one", "PDF");
			
			$uploads     = wp_upload_dir();
			$upload_path = $uploads['basedir'] . '/invoices';
			
			// Check if invoices directory exists and is writable
			if (!is_dir($upload_path)) {
				woocomm_invfox__trace("Invoices directory doesn't exist, attempting to create it: " . $upload_path, "PDF Error");
				$dir_created = wp_mkdir_p($upload_path);
				
				if (!$dir_created) {
					woocomm_invfox__trace("Failed to create invoices directory", "PDF Error");
					$notices = get_option('cebelcabiz_deferred_admin_notices', array());
					$notices[] = "NAPAKA: Ni bilo mogoče ustvariti mape za PDF dokumente. Preverite pravice dostopa do mape: " . $upload_path;
					update_option('cebelcabiz_deferred_admin_notices', $notices);
					return false;
				}
				
				// Set proper permissions
				chmod($upload_path, 0755);
			}
			
			// Verify directory is writable
			if (!is_writable($upload_path)) {
				woocomm_invfox__trace("Invoices directory is not writable: " . $upload_path, "PDF Error");
				$notices = get_option('cebelcabiz_deferred_admin_notices', array());
				$notices[] = "NAPAKA: Mapa za PDF dokumente ni zapisljiva. Preverite pravice dostopa do mape: " . $upload_path;
				update_option('cebelcabiz_deferred_admin_notices', $notices);
				return false;
			}
			
			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
			$api->setDebugHook( "woocomm_invfox__trace" );

			$file = $api->downloadPDF( 0, $order->get_id(), $upload_path, $resource, '' );
			
			// Check if download was successful
			if ($file === false) {
				woocomm_invfox__trace("PDF download failed for resource: " . $resource, "PDF Error");
				
				// Add admin notice about the failure
				$notices = get_option('cebelcabiz_deferred_admin_notices', array());
				$notices[] = "NAPAKA: Ni bilo mogoče prenesti PDF dokumenta (" . $resource . "). Preverite povezavo s strežnikom Čebelca BIZ.";
				update_option('cebelcabiz_deferred_admin_notices', $notices);
				
				return false;
			}
			
			// Store the PDF path with the resource-specific meta key
			update_post_meta( $order->get_id(), $meta_key, $file );
			woocomm_invfox__trace("Saved new PDF path to post meta with key: " . $meta_key, "PDF");
			woocomm_invfox__trace("PDF download completed", "PDF");

			return $file;
		}
        
		/**
		 * Attach invoice PDF to email and add order note
		 * 
		 * @param array $attachments Existing attachments
		 * @param string $status Email status
		 * @param WC_Order $order Order object
		 * @return array Modified attachments
		 */
		function _attach_invoice_pdf_to_email( $attachments, $status, $order ) {
			woocomm_invfox__trace("Processing email attachment for status: " . $status, "Email");
            if ( isset( $status ) && in_array( $status, array( 'customer_on_hold_order' ) ) ) {
				woocomm_invfox__trace("Processing on-hold order with setting: " . $this->conf['on_order_on_hold'], "Email");

                if ( $this->conf['on_order_on_hold'] == "create_proforma_email" ) {
                    
                    woocomm_invfox__trace("Attaching proforma invoice PDF for on-hold order", "Email");
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					
					if ($path === false) {
						woocomm_invfox__trace("Failed to attach proforma PDF - download failed", "Email Error");
						$order->add_order_note( "NAPAKA: PDF predračuna ni bilo mogoče poslati po e-pošti zaradi napake pri prenosu" );
					} else {
						$attachments[] = $path;
						woocomm_invfox__trace( $attachments );
						
						// Add order note that PDF was sent by email
						$order->add_order_note( "PDF predračuna je bil poslan po e-pošti" );
					}
				}
				return $attachments;
            }
            else if ( isset( $status ) && in_array( $status, array( 'customer_processing_order' ) ) ) {
                
                if ( $this->conf['on_order_processing'] == "create_proforma_email" ) {
                    
                    woocomm_invfox__trace("Attaching proforma invoice PDF for processing order", "Email");
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					
					if ($path === false) {
						woocomm_invfox__trace("Failed to attach proforma PDF - download failed", "Email Error");
						$order->add_order_note( "NAPAKA: PDF predračuna ni bilo mogoče poslati po e-pošti zaradi napake pri prenosu" );
					} else {
						$attachments[] = $path;
						woocomm_invfox__trace( $attachments );
						
						// Add order note that PDF was sent by email
						$order->add_order_note( "PDF predračuna je bil poslan po e-pošti" );
					}
				}
				return $attachments;
                
            } else if ( isset( $status ) && in_array( $status, array( 'customer_completed_order' ) ) ) {

                if ( $this->conf['on_order_completed'] == "create_proforma_email") {
                    
                    woocomm_invfox__trace("Attaching proforma invoice PDF for completed order", "Email");
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					
					if ($path === false) {
						woocomm_invfox__trace("Failed to attach proforma PDF - download failed", "Email Error");
						$order->add_order_note( "NAPAKA: PDF predračuna ni bilo mogoče poslati po e-pošti zaradi napake pri prenosu" );
					} else {
						$attachments[] = $path;
						woocomm_invfox__trace( $attachments );
						
						// Add order note that PDF was sent by email
						$order->add_order_note( "PDF predračuna je bil poslan po e-pošti" );
					}
                    
				} else if ($this->conf['on_order_completed'] == "create_invoice_complete_email" ||
                           $this->conf['on_order_completed'] == "create_invoice_complete_paid_email" ||
                           $this->conf['on_order_completed'] == "create_invoice_complete_paid_inventory_email" ||
                           $this->conf['on_order_completed'] == "email" ) {
                    
                    woocomm_invfox__trace("Attaching invoice PDF for completed order", "Email");
					$path = $this->_woocommerce_order_invoice_pdf( $order, "invoice-sent" );
					
					if ($path === false) {
						woocomm_invfox__trace("Failed to attach invoice PDF - download failed", "Email Error");
						$order->add_order_note( "NAPAKA: PDF računa ni bilo mogoče poslati po e-pošti zaradi napake pri prenosu" );
					} else {
						$attachments[] = $path;
						woocomm_invfox__trace( $attachments );
						
						// Add order note that PDF was sent by email
						$order->add_order_note( "PDF računa je bil poslan po e-pošti" );
					}
				}
				return $attachments;

			} else {
				return array();
			}
		}
    }   
    
    $WC_Cebelcabiz = new WC_Cebelcabiz( __FILE__ );
}


function woocomm_invfox_get_attributes( $product ) {

    $formatted_attributes = array();

    $attributes = $product->get_attributes();

    foreach ( $attributes as $attr => $attr_deets ) {

        $attribute_label = wc_attribute_label( $attr );

        if ( isset( $attributes[ $attr ] ) || isset( $attributes[ 'pa_' . $attr ] ) ) {

            $attribute = isset( $attributes[ $attr ] ) ? $attributes[ $attr ] : $attributes[ 'pa_' . $attr ];

            if ( $attribute['is_taxonomy'] ) {

                $formatted_attributes[ $attribute_label ] = implode( ', ', wc_get_product_terms( $product->id, $attribute['name'], array(
                    'fields' => 'names'
                ) ) );

            } else {

                $formatted_attributes[ $attribute_label ] = $attribute['value'];
            }

        }
    }

    return $formatted_attributes;
}

register_activation_hook( __FILE__, array( 'WC_Cebelcabiz', 'myplugin_activate' ) );

function woocomm_invfox_prettify_slug( $t ) {
    return str_replace( "-", " ", ucfirst( $t ) );
}

function woocomm_invfox_get_item_attributes( $item ) {

    $res = "";

    foreach ( $item as $key => $val ) {

        if ( strpos( $key, "pa_" ) !== false ) {

            $res .= ( $res === "" ? "" : "\n" ) . woocomm_invfox_prettify_slug( substr( $key, 3 ) ) . ": " . woocomm_invfox_prettify_slug( $val );

        }
    }

    return $res;
}

function woocomm_invfox_get_order_item_variations( $item ) {

    $res = "";
    
    $product = $item->get_product();
    
    // Only for product variation
    if( $product->is_type('variation') ){

        // Get the variation attributes
        $variation_attributes = $product->get_variation_attributes();
        
        // Loop through each selected attributes
        foreach($variation_attributes as $attribute_taxonomy => $term_slug ){

            // Get product attribute name or taxonomy
            $taxonomy = str_replace('attribute_', '', $attribute_taxonomy );
            
            // The label name from the product attribute
            $attribute_name = wc_attribute_label( $taxonomy, $product );
            
            // The term name (or value) from this attribute
            if( taxonomy_exists($taxonomy) ) {
                $term = get_term_by( 'slug', $term_slug, $taxonomy );
                $attribute_value = $term && isset($term->name) ? $term->name : $term_slug;
            } else {
                $attribute_value = $term_slug; // For custom product attributes
            }
            $res .= "- " . $attribute_name . ": " . $attribute_value . "\n";
        }
    }
    return $res;
}

/**
 * Parse VAT levels from a comma-separated string
 * 
 * @param string $string Comma-separated VAT levels
 * @return array Array of VAT levels as floats
 */
function parseVatLevels($string) {
    if (empty($string)) {
        // Default VAT levels if none provided
        return array(0, 5, 9.5, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27);
    }
    
    try {
        // Split the string into an array of strings using str_getcsv()
        $array = str_getcsv($string);
        
        // Convert each string to a float and filter out invalid values
        $doubles = array();
        foreach ($array as $value) {
            $float = floatval(trim($value));
            if ($float >= 0) { // Only accept non-negative values
                $doubles[] = $float;
            }
        }
        
        // If no valid values were found, return default values
        if (empty($doubles)) {
            return array(0, 5, 9.5, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27);
        }
        
        // Return the resulting array of doubles
        return $doubles;
    } catch (Exception $e) {
        woocomm_invfox__trace("Error parsing VAT levels: " . $e->getMessage(), "ERROR");
        // Return default values in case of error
        return array(0, 5, 9.5, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27);
    }
}

/**
 * Map payment methods from WooCommerce to Cebelca BIZ
 * 
 * @param string $key WooCommerce payment method
 * @param string $methodsMap Payment methods mapping string
 * @return string|int Cebelca BIZ payment method or error code
 */
function mapPaymentMethods($key, $methodsMap) {
    try {
        if (empty($key) || empty($methodsMap)) {
            woocomm_invfox__trace("Empty payment method or mapping", "WARNING");
            return -2; // Missing data
        }
        
        $mmap = str_replace("&gt;", ">", $methodsMap);
        
        woocomm_invfox__trace($mmap, "Payment methods map");
        
        // Check if the string is in the correct format using a regular expression
        if (!preg_match('/([a-zA-Z0-9čČšŠžŽ\/_\-\(\)\* ]+->[a-zA-Z0-9čČšŠžŽ_\- ]+)+/', $mmap)) {
            woocomm_invfox__trace("Invalid payment methods map format", "ERROR");
            return -1; // Invalid format
        }
        
        // Split the string into pairs
        $pairs = explode(';', $mmap);
        
        // Iterate through each pair
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue; // Skip empty pairs
            }
            
            // Check if the pair contains the delimiter
            if (strpos($pair, '->') === false) {
                continue; // Skip invalid pairs
            }
            
            // Split the pair into key and value
            list($pairKey, $value) = explode('->', $pair);
            
            $pairKey = trim($pairKey);
            $value = trim($value);
            
            // Check for exact match
            if ($pairKey === $key) {
                return $value;
            }
            
            // Check for wildcard match
            if (strpos($pairKey, "*") !== false) {
                $pattern = str_replace("*", ".*", preg_quote($pairKey, '/'));
                if (preg_match('/^' . $pattern . '$/i', $key)) {
                    return $value;
                }
            }
        }
        
        // Return -2 if no matching key is found
        woocomm_invfox__trace("No matching payment method found for: $key", "WARNING");
        return -2;
    } catch (Exception $e) {
        woocomm_invfox__trace("Error mapping payment methods: " . $e->getMessage(), "ERROR");
        return -1;
    }
}

/**
 * Check if a payment method is amongst the fiscalized payment methods
 * 
 * @param string $po Payment method
 * @param string $options Fiscalized payment methods
 * @return bool True if payment method should be fiscalized
 */
function isPaymentAmongst($po, $options) {
    try {
        if (empty($options)) {
            return false;
        }
        
        // Check if there is a * , if it is, answer true for any payment method
        if (strpos($options, "*") !== false) {
            return true;
        }
        
        if (empty($po)) {
            return false;
        }
        
        // Convert both strings to lowercase for case-insensitive search
        $po = strtolower(trim($po));
        $options = strtolower(trim($options));
        
        // Split options by comma and check each one
        $optionsArray = explode(',', $options);
        foreach ($optionsArray as $option) {
            $option = trim($option);
            if (empty($option)) {
                continue;
            }
            
            // Check for exact match or substring match
            if ($option === $po || strpos($po, $option) !== false) {
                return true;
            }
        }
        
        // Return false if no match found
        return false;
    } catch (Exception $e) {
        woocomm_invfox__trace("Error checking payment method: " . $e->getMessage(), "ERROR");
        return false;
    }
}

/**
 * Calculate the precise Slovenian VAT rate based on net price and VAT value
 * 
 * @param float $netPrice Net price
 * @param float $vatValue VAT value
 * @param array $vatLevels Available VAT levels
 * @return float Closest matching VAT level
 */
function calculatePreciseSloVAT($netPrice, $vatValue, $vatLevels) {
    try {
        // Handle zero net price
        if (empty($netPrice) || $netPrice == 0) {
            return 0;
        }
        
        // Handle zero VAT value
        if (empty($vatValue) || $vatValue == 0) {
            return 0;
        }
        
        // Calculate VAT percentage
        $calculatedVat = round($vatValue / $netPrice * 100, 2);
        
        // Find closest VAT level
        $closestVat = 0;
        $minDiff = PHP_FLOAT_MAX;
        
        foreach ($vatLevels as $vatLevel) {
            $diff = abs($calculatedVat - $vatLevel);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestVat = $vatLevel;
            }
        }
        
        // If the difference is too large, return -1 to indicate an error
        if ($minDiff > 2.0) {
            woocomm_invfox__trace("Calculated VAT ($calculatedVat%) is too far from any known VAT level", "WARNING");
            return -1;
        }
        
        return $closestVat;
    } catch (Exception $e) {
        woocomm_invfox__trace("Error calculating VAT: " . $e->getMessage(), "ERROR");
        return -1;
    }
}


function my_api_endpoint_callback() {
    $sku = $_GET['sku'];
    $quantity = $_GET['quantity'];
    // Your code to update inventory quantity based on the provided SKU and quantity
}

function get_products_by_status() {
    update_product_quantity(array( "CHI1" => 4321 ));
    return "!GET PRODUCTS BY STATUS TEST!";
}

function create_custom_endpoint() {
    register_rest_route( 'custom', '/products/(?P<status>\w+)', array(
        'methods'  => 'GET',
        'callback' => 'get_products_by_status',
        'args'     => array(
            'status' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param) && preg_match('/^(instock|outofstock)$/', $param);
                },
            ),
        ),
    ) );
}

function update_product_quantity($sku_quantity_array) {
    foreach ($sku_quantity_array as $sku => $quantity) {
        $product_id = wc_get_product_id_by_sku($sku);
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_stock_quantity($quantity);
            $product->save();
        }
    }
}
