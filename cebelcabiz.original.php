<?php
/**
 * Plugin Name: Cebelca BIZ
 * Plugin URI:
 * Description: Connects WooCommerce to Cebelca.biz for invoicing and optionally inventory
 * Version: 0.0.111
 * Author: JankoM
 * Author URI: http://refaktorlabs.com
 * Developer: Janko M.
 * Developer URI: http://refaktorlabs.com
 * Text Domain: woocommerce-cebelcabiz
 * Domain Path: /languages
 *
 */

// Status: Beta

if ( ! class_exists( 'WC_Cebelcabiz' ) ) {

    // ini_set('display_errors', 1);
	// error_reporting(E_ALL);
    // ini_set("log_errors", 1);

    // SET TO TRUE OF FALSE TO DEBUG
    define("WOOCOMM_INVFOX_DEBUG", false);
    
	function woocomm_invfox__trace( $x, $y = "" ) {
        //global $woocomm_invfox__debug;
        if (WOOCOMM_INVFOX_DEBUG) {
            error_log( "WC_Cebelcabiz: " . ( $y ? $y . " " : "" ) . print_r( $x, true ) );
        }
	}
    
	require_once( dirname( __FILE__ ) . '/lib/invfoxapi.php' );
	require_once( dirname( __FILE__ ) . '/lib/strpcapi.php' );
                                           
    $conf = null;
    
	class WC_Cebelcabiz {

		/**
		 * Construct the plugin.
		 */
		public function __construct() {
            
			$this->conf = get_option( 'woocommerce_cebelcabiz_settings' );

            // error_log($this->conf);
			if ( $this->conf ) {
                if ( !array_key_exists('api_domain', $this->conf) ) {
                    $this->conf['api_domain'] = "www.cebelca.biz";
                }
                if ( !array_key_exists('app_name', $this->conf) ) {
                    $this->conf['app_name'] = "Cebelca BIZ";
                }
                if ( !array_key_exists('use_shop_id_for_docnum', $this->conf) ) {
                    $this->conf['use_shop_id_for_docnum'] = false;
                }
                if ( !array_key_exists('fiscal_test_mode', $this->conf) ) {
                    $this->conf['fiscal_test_mode'] = false;
                }
            }
            
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			// this sets callbacks on order status changes
			add_action( 'woocommerce_order_status_on-hold', array( $this, '_woocommerce_order_status_on_hold' ) );
			add_action( 'woocommerce_order_status_processing', array( $this, '_woocommerce_order_status_processing' ) );
			add_action( 'woocommerce_order_status_completed', array( $this, '_woocommerce_order_status_completed' ) );

            add_action( 'rest_api_init', 'create_custom_endpoint' );
            
			// collects attachment on order complete email sent
			add_filter( 'woocommerce_email_attachments', array( $this, '_attach_invoice_pdf_to_email' ), 10, 3 );

			add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			if ( $this->conf && $this->conf['order_actions_enabled'] ) {
				add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );
				// process the custom order meta box order action
				add_action( 'woocommerce_order_action_cebelcabiz_create_invoice', array(
					$this,
					'process_custom_order_action_invoice'
				) );
				add_action( 'woocommerce_order_action_cebelcabiz_create_proforma', array(
					$this,
					'process_custom_order_action_proforma'
				) );
				add_action( 'woocommerce_order_action_cebelcabiz_create_advance', array(
					$this,
					'process_custom_order_action_advance'
				) );
				add_action( 'woocommerce_order_action_cebelcabiz_create_invt_sale', array(
					$this,
					'process_custom_order_action_invt_sale'
				) );
				add_action( 'woocommerce_order_action_cebelcabiz_check_invt_items', array(
					$this,
					'process_custom_order_action_check_invt_items'
				) );
				add_action( 'woocommerce_order_action_cebelcabiz_mark_invoice_paid', array(
					$this,
					'process_custom_order_action_mark_invoice_paid'
                ) );
			}

			$translArr = array(
				0 => "invoice",
				1 => "proforma",
				2 => "inventory"
			);
		}


        static function myplugin_activate() {
            
            $upload = wp_upload_dir();
            $upload_dir = $upload['basedir'];
            $upload_dir = $upload_dir . '/invoices';
            if (! is_dir($upload_dir)) {
                mkdir( $upload_dir, 0700 );
            }
        }
 
		/**
		 * Initialize the plugin.
		 */
		public function init() {
			// Checks if WooCommerce is installed.
			if ( class_exists( 'WC_Cebelcabiz' ) ) {

				// Include our integration class.
				include_once 'includes/class-wc-integration-cebelcabiz.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

			} else {
				// throw an admin error if you like
			}
		}

		/*
		 * Show admin notices from stack
		 */
		function admin_notices() {
			if ( $notices = get_option( 'cebelcabiz_deferred_admin_notices' ) ) {
				foreach ( $notices as $notice ) {
                    if (strpos($notice, "NAPAKA") === false) {
                        echo "<div class='updated'><p>$notice</p></div>";
                    } else {
                        echo "<div class='updated' style='color: red;'><p>$notice</p></div>";
                    }
				}
				delete_option( 'cebelcabiz_deferred_admin_notices' );
			}
		}

		/**
		 * Add our items for order actions box
		 */
		function add_order_meta_box_actions( $actions ) {
			$actions['cebelcabiz_create_invoice']    = __( $this->conf['app_name'] . ': Make invoice', 'woocom-invfox' );
			$actions['cebelcabiz_mark_invoice_paid'] = __( $this->conf['app_name'] . ': Mark invoice paid', 'woocom-invfox' );
			$actions['cebelcabiz_create_proforma']   = __( $this->conf['app_name'] . ': Make proforma', 'woocom-invfox' );
			$actions['cebelcabiz_create_advance']    = __( $this->conf['app_name'] . ': Make advance', 'woocom-invfox' );

			//if ( $this->conf['use_inventory'] == "yes" ) {
            $actions['cebelcabiz_check_invt_items'] = __( $this->conf['app_name'] . ': Check inventory', 'woocom-invfox' );
            $actions['cebelcabiz_create_invt_sale'] = __( $this->conf['app_name'] . ': Make invent. sale', 'woocom-invfox' );
                //}

			return $actions;
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Integration_Cebelcabiz';

			return $integrations;
		}

		function process_custom_order_action_invoice( $order ) {
			$this->_make_document_in_invoicefox( $order, "invoice_draft" );
		}

		function process_custom_order_action_proforma( $order ) {
			$this->_make_document_in_invoicefox( $order, "proforma" );
		}
		
		function process_custom_order_action_advance( $order ) {
			$this->_make_document_in_invoicefox( $order, "advance_draft" );
		}

		function process_custom_order_action_invt_sale( $order ) {
			$this->_make_document_in_invoicefox( $order, "inventory" );
		}

		function process_custom_order_action_full_invoice( $order ) {
			$this->_make_document_in_invoicefox( $order, "invoice_complete" );
		}

		function process_invoice_download( $order ) {
			$this->_woocommerce_order_invoice_pdf( $order, "invoice-sent" );
		}

		function process_custom_order_action_mark_invoice_paid( $order ) {
			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], false );
			$api->setDebugHook( "woocomm_invfox__trace" );
            $currentPM = $order->get_payment_method_title();
            woocomm_invfox__trace( "================ MARKING PAID ===============" );
            woocomm_invfox__trace( $currentPM );
            woocomm_invfox__trace( $this->conf['payment_methods_map'] );
            $payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
            woocomm_invfox__trace( $payment_method );

            $notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
            if($payment_method == -2) {
                $notices[] = "NAPAKA: Način plačila $currentPM manjka v nastavitvah pretvorbe.";
                $payment_method = "-";
            }
            else if($payment_method == -1) {
                $notices[] = "NAPAKA: Napačna oblika nastavitve: Pretvorba načinov plačila.";
                $payment_method = "-";
            }
            woocomm_invfox__trace( "CALLING API" );
            $res = $api->markInvoicePaid( $order->get_id(), $payment_method );
            woocomm_invfox__trace( $res );
            // TODO -- poglej kaj je vrnila Čebelca in zapiši v notices
            $notices[] = "Plačilo zabeleženo";

			update_option( 'cebelcabiz_deferred_admin_notices', $notices );
		}

		function process_custom_order_action_check_invt_items( $order ) {
			$items = array();

			foreach ( $order->get_items() as $item ) {
				if ( 'line_item' == $item['type'] ) {
					$product = $item->get_product(); // $order->get_product_from_item( $item );
					$items[] = array(
						'code' => $product->get_sku(),
						'qty'  => $item['qty']
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
					$msg   .= ( $first ? "" : ", " ) . $item['code'] . ": " . ( $item['result'] == null ? "item code not found" : ( $item['result'] >= 0 ? "OK, on inventory (+ {$item['result']})" : "less inventory ({$item['result']})" ) );
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
              
              woocomm_invfox__trace( "================ BEFORE BEFORE ===============" );
              woocomm_invfox__trace( $this->conf['on_order_completed'] );
              woocomm_invfox__trace(strpos($this->conf['on_order_completed'], "_paid_") !== false);
              woocomm_invfox__trace(strpos($this->conf['on_order_completed'], "_inventory_") !== false );

              
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

			woocomm_invfox__trace( "================ BEGIN MAKING DOC ===============" );
			woocomm_invfox__trace(             $this->conf['document_to_make'] );
			woocomm_invfox__trace(             $order->get_payment_method() );
			woocomm_invfox__trace(             $order->get_payment_method_title() );

			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
//			$api->setDebugHook( "woocomm_invfox__trace" );

			$vatNum = get_post_meta( $order->get_id(), 'VAT Number', true );
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), 'vat_number', true ); }
			if (!$vatNum) { $vatNum = get_post_meta( $order->get_id(), '_vat_number', true ); }

			$r = $api->assurePartner( array(
				'name'           => $order->get_billing_first_name() . " " . $order->get_billing_last_name() . ( $order->get_billing_company() ? ", " : "" ) . $order->get_billing_company(),
				'street'         => $order->get_billing_address_1() . "\n" . $order->get_billing_address_2(),
				'postal'         => $order->get_billing_postcode(),
				'city'           => $order->get_billing_city(),
				'country'        => $order->get_billing_country(),
				'vatid'          => $vatNum, // TODO -- find where the data is
				'phone'          => $order->get_billing_phone(), //$c->phone.($c->phone_mobile?", ":"").$c->phone_mobile,
				'website'        => "",
				'email'          => $order->get_billing_email(),
				'notes'          => '',
				'vatbound'       => ! ! $vatNum, //!!$c->vat_number, TODO -- after (2)
				'custaddr'       => '',
				'payment_period' => $this->conf['customer_general_payment_period'],
				'street2'        => ''
			) );

			if ( $r->isOk() ) {

                $vatLevelsOK = true;
                
                woocomm_invfox__trace( "================ BEGIN MAKING DOC 1 ===============" );

				$clientIdA = $r->getData();
				$clientId  = $clientIdA[0]['id'];
				$date1     = $api->_toSIDate( date( 'Y-m-d' ) ); //// TODO LONGTERM ... figure out what we do with different Dates on api side (maybe date optionally accepts dbdate format)
				$invid     = $this->conf['use_shop_id_for_docnum'] ? str_pad( $order->get_id(), 5, "0", STR_PAD_LEFT ) : "";
				$body2     = array();

				foreach ( $order->get_items() as $item ) {
					if ( 'line_item' == $item['type'] ) {
						$product        = $item->get_product(); // $order->get_product_from_item( $item );
                        woocomm_invfox__trace( "PRODUCT::: ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////" );
                        woocomm_invfox__trace( $product );

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
                        woocomm_invfox__trace("PRICE QTY SUBTOTAL");
                        woocomm_invfox__trace($price);
                        woocomm_invfox__trace($quantity);
                        woocomm_invfox__trace($subtotal);
                        woocomm_invfox__trace("DISCOUNT");
                        woocomm_invfox__trace($discounted_price);
                        woocomm_invfox__trace($discount_percentage);
                   
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
					woocomm_invfox__trace( "adding shipping" );

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

					$body2[] = array(
						'title'    => "Dostava - " . $order->get_shipping_method(),
						'qty'      => 1,
						'mu'       => '',
						'price'    => $order->get_shipping_total(),
						'vat'      => calculatePreciseSloVAT($order->get_shipping_total(), $order->get_shipping_tax(), $vatLevels),
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
					woocomm_invfox__trace( "before create invoice call" );

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
                        
                        woocomm_invfox__trace( "--- R2 IS OK ----" );
                            
                        if ( $vatLevelsOK ) {

                            woocomm_invfox__trace( "--- VAT LEVEL OK ----" );
    
                            
                            woocomm_invfox__trace( "--- BEFORE FINALIZE ----" );
                            woocomm_invfox__trace( $this->conf['fiscal_mode'] );
                            $invA = $r2->getData();
                            woocomm_invfox__trace( $order->get_payment_method_title() );
                            woocomm_invfox__trace( $this->conf['fiscalize_payment_methods'] );
                            if ( $this->conf['fiscal_mode'] == "yes" &&
                                 isPaymentAmongst($order->get_payment_method_title(), $this->conf['fiscalize_payment_methods'])) {                            
                                woocomm_invfox__trace( "--- fiscal ----" );
                                woocomm_invfox__trace( $this->conf );
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
                                    woocomm_invfox__trace( "ERROR: MISSING ID_LOCATION, OP TAX ID, OP NAME" );
                                    
                                }
                            } else {
                                woocomm_invfox__trace( "--- non fiscal ----" );
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
                        
						woocomm_invfox__trace( "--- BEFORE PAID  ----" );

                        if ($markPaid) {
                          woocomm_invfox__trace( "--- BEFORE PAID 2  ----" );

                          //                          			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
                          //			$api->setDebugHook( "woocomm_invfox__trace" );


                          $currentPM = $order->get_payment_method_title();
                          woocomm_invfox__trace( "================ MARKING PAID ===============" );
                          woocomm_invfox__trace( $currentPM );
                          woocomm_invfox__trace( $this->conf['payment_methods_map'] );
                          $payment_method = mapPaymentMethods($currentPM, $this->conf['payment_methods_map']);
                          woocomm_invfox__trace( $payment_method );
                          
                          $notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
                          if($payment_method == -2) {
                              $notices[] = "NAPAKA: Način plačila $currentPM manjka v nastavitvah pretvorbe. Plačilo v Čebelci ni bilo zabeleženo.";
                              $payment_method = "-";
                          }
                          else if($payment_method == -1) {
                              $notices[] = "NAPAKA: Napačna oblika nastavitve: Pretvorba načinov plačila. Plačilo v Čebelci ni bilo zabeleženo.";
                              $payment_method = "-";
                          }
                          woocomm_invfox__trace( "CALLING API" );
                          $res = $api->markInvoicePaid( $order->get_id(), $payment_method );
                          woocomm_invfox__trace( $res );
                          // TODO -- poglej kaj je vrnila Čebelca in zapiši v notices
                          $notices[] = "Plačilo zabeleženo";
                          
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
                        
						woocomm_invfox__trace( "--- BEFORE INVT  ----" );
                        
                        if ($decreaseInventory) {
                          woocomm_invfox__trace( "--- BEFORE INVT 2  ----" );
                          $api->makeInventoryDocOutOfInvoice(  $invA[0]['id'], $this->conf['from_warehouse_id'], $clientId );
                          $notices   = get_option( 'cebelcabiz_deferred_admin_notices', array() );
                          $notices[] = "Inventory doc created";
                          update_option( 'cebelcabiz_deferred_admin_notices', $notices );
                        }

                        if ( $vatLevelsOK ) { 
                            $uploads = wp_upload_dir();
                            $upload_path    = $uploads['basedir'] . "/invoices";
                            
                            //$filename = $api->downloadInvoicePDF( $order->id, $path );
                            $filename = $api->downloadPDF( 0, $order->get_id(), $upload_path, 'invoice-sent', '' );
                            
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
					woocomm_invfox__trace( "before create proforma call" );

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

                        woocomm_invfox__trace( "** UPLOADING INVOICE **" );

                        $uploads     = wp_upload_dir();
						$upload_path    = $uploads['basedir'] . "/invoices";
                        $filename = $api->downloadPDF( 0, $order->get_id(), $upload_path, 'preinvoice', '' );
                        
						add_post_meta( $order->get_id(), 'invoicefox_attached_pdf', $filename );
						$order->save();
                        woocomm_invfox__trace($filename );
                        woocomm_invfox__trace( "** UPLOADING INVOICE END **" );

                        $invA = $r2->getData();
                        $docnum = $invA[0]['title'] ? $invA[0]['title'] : "OSNUTEK";
						$order->add_order_note( "Predračun {$docnum} je bil ustvarjen na {$this->conf['app_name']}." );
                        //						$order->add_order_note( "ProForma Invoice No. {$invA[0]['title']} was created at {$this->conf['app_name']}." );
					}

				} elseif ( $this->conf['document_to_make'] == 'inventory' ) {
					woocomm_invfox__trace( "before create inventory sale call" );

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
				woocomm_invfox__trace( "after create invoice" );
			}

			return true;
		}

		/**
		 * function that downloads pdf
		 */
		function _woocommerce_order_invoice_pdf( $order, $resource ) {
			woocomm_invfox__trace( "================ BEGIN PDF DOWNLOAD ===============" );

			$api = new InvfoxAPI( $this->conf['api_key'], $this->conf['api_domain'], true );
			$api->setDebugHook( "woocomm_invfox__trace" );

			$uploads     = wp_upload_dir();
			$upload_path = $uploads['basedir'] . '/invoices';

			$file = $api->downloadPDF( 0, $order->get_id(), $upload_path, $resource, '' );

			woocomm_invfox__trace( "================ END PDF DOWNLOAD ===============" );

			return $file;
		}
        
		function _attach_invoice_pdf_to_email( $attachments, $status, $order ) {
            
            if ( isset( $status ) && in_array( $status, array( 'customer_on_hold_order' ) ) ) {
                
                if ( $this->conf['on_order_on_hold'] == "create_proforma_email" ) {
                    
                    woocomm_invfox__trace( "================ ATTACH PROFORMA FILTER CALLED  ===============" );
                    woocomm_invfox__trace( "================ ON HOLD  ===============" );
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					$attachments[] = $path;
                    woocomm_invfox__trace( $attachments );
				}
				return $attachments;
            }
            else if ( isset( $status ) && in_array( $status, array( 'customer_processing_order' ) ) ) {
                
                if ( $this->conf['on_order_processing'] == "create_proforma_email" ) {
                    
                    woocomm_invfox__trace( "================ ATTACH PROFORMA FILTER CALLED  ===============" );
                    woocomm_invfox__trace( "================ PROCESSING  ===============" );
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					$attachments[] = $path;
                    woocomm_invfox__trace( $attachments );
				}
				return $attachments;
                
            } else if ( isset( $status ) && in_array( $status, array( 'customer_completed_order' ) ) ) {

                if ( $this->conf['on_order_completed'] == "create_proforma_email") {
                    
                    woocomm_invfox__trace( "================ ATTACH PROFORMA FILTER CALLED  ===============" );
                    woocomm_invfox__trace( "================ COMPLETED ===============" );
					$path = $this->_woocommerce_order_invoice_pdf( $order, "preinvoice" );
					$attachments[] = $path;
                    woocomm_invfox__trace( $attachments );
                    
				} else if ($this->conf['on_order_completed'] == "create_invoice_complete_email" ||
                    $this->conf['on_order_completed'] == "create_invoice_complete_paid_email" ||
                    $this->conf['on_order_completed'] == "create_invoice_complete_paid_inventory_email" ||
                    $this->conf['on_order_completed'] == "email" ) {
                    
                    woocomm_invfox__trace( "================ ATTACH FILTER CALLED  ===============" );
                    woocomm_invfox__trace( "================ COMPLETED  ===============" );
					$path = $this->_woocommerce_order_invoice_pdf( $order, "invoice-sent" );
					$attachments[] = $path;
                    woocomm_invfox__trace( $attachments );
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
                $attribute_value = get_term_by( 'slug', $term_slug, $taxonomy )->name;
            } else {
                $attribute_value = $term_slug; // For custom product attributes
            }
            $res .= "- " . $attribute_name . ": " . $attribute_value . "\n";
        }
    }
    return $res;
}

function parseVatLevels($string) {
    // Split the string into an array of strings using str_getcsv()
    $array = str_getcsv($string);
    
    // Convert each string to a double using the floatval() function
    $doubles = array_map('floatval', $array);

    // Return the resulting array of doubles
    return $doubles;
}


function mapPaymentMethods($key, $methodsMap){

    $mmap = str_replace("&gt;", ">", $methodsMap);

    woocomm_invfox__trace( $mmap );
    
    // Check if the string is in the correct format using a regular expression
    if (!preg_match('/([a-zA-Z0-9čČšŠžŽ\/_\-\(\)\* ]+->[a-zA-Z0-9čČšŠžŽ_\- ]+)+/', $mmap)) {
        return -1; // Return -1 if the format is incorrect
    }

    // Split the string into pairs
    $pairs = explode(';', $mmap);

    // Iterate through each pair
    foreach ($pairs as $pair) {
        if (empty($pair)) {
            continue; // Skip empty pairs (e.g., the last one after the final semicolon)
        }

        // Split the pair into key and value
        list($pairKey, $value) = explode('->', $pair);

        // Check if the current key matches the requested key
        if (trim($pairKey) == $key) {
            return $value; // Return the value if a match is found
        }
	if (strpos("*", $pairKey) !== false) { // anywhere in the string for now 
	        if (strpos($key, $pairKey) !== false) {
	            return $value; // Return the value if a partial match is found
	        }
	}
	    
    }

    // Return -2 if no matching key is found
    return -2;

}

function isPaymentAmongst($po, $options){ 
    // check if there is a * , if it is, answer true for any payment method
    if (strpos($options, "*") !== false) {
        return true;    
    }
    
    // convert both strings to lowercase for case-insensitive search
    $po = strtolower($po);
    $options = strtolower($options);

    // return true if $po is found within $options, otherwise return false
    return strpos($options, $po) !== false;
}

function calculatePreciseSloVAT($netPrice, $vatValue, $vatLevels) {
    if ($netPrice == 0) {
        return 0;
    }

    $calculatedVat = round($vatValue / $netPrice * 100, 1);
    $closestVat = 0;
    $minDiff = 1000;

    foreach ($vatLevels as $vatLevel) {
        $diff = abs($calculatedVat - $vatLevel);
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closestVat = $vatLevel;
        }
    }
    return $closestVat;
}

function calculatePreciseSloVAT_OLD($netPrice, $vatValue, $vatLevels) {
	// because of new EU rules all EU VAT levels are valid here
	// $vatLevels = array();
    if ($netPrice != 0) {
        $vat1 = round( $vatValue / $netPrice * 100, 1);
        $vat = -1;
        foreach ($vatLevels as $vatLevel) {
            if (abs($vat1 - $vatLevel) < 1.5) {
                $vat = $vatLevel;
            }
        }
	} else {
        $vat = 0;
    }
	return $vat;
}


function my_api_endpoint_callback() {
    $sku = $_GET['sku'];
    $quantity = $_GET['quantity'];
    // Your code to update inventory quantity based on the provided SKU and quantity
}

function get_products_by_status() {
    update_product_quantity(array( "CHI1" => 4321 ));
    return "YOYO!!!";
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

?>
