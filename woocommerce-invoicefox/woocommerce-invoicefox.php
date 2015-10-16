<?php
/**
 * Plugin Name: WooCommerce InvoiceFox integration
 * Plugin URI: 
 * Description: Connects WooCommerce to InvoiceFox/Cebelca.biz for invoicing and optionally inventory
 * Version: 0.0.4
 * Author: JankoITM
 * Author URI: http://refaktorlabs.com
 * Developer: Janko M.
 * Developer URI: http://refaktorlabs.com
 * Text Domain: woocommerce-invoicefox
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooThemes.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Status: Alpha
if ( ! class_exists( 'WC_InvoiceFox' ) ) {

  //ini_set('display_errors', 1);
  //error_reporting(E_ALL ^ E_NOTICE);
  
  require_once(dirname(__FILE__).'/lib/invfoxapi.php');
  require_once(dirname(__FILE__).'/lib/strpcapi.php');
  
  function woocomm_invfox__trace($x, $y="") { error_log("WC_InvoiceFox: ".($y?$y." ":"").print_r($x,true)); }
  
  $conf = null;
  
  class WC_InvoiceFox {
    /**
     * Construct the plugin.
     */
    public function __construct() {
      add_action( 'plugins_loaded', array( $this, 'init' ) );
      // this in for on status complete action
      //      add_action( 'woocommerce_order_status_completed',
      // array($this, '_woocommerce_order_status_completed'));
      add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );
      add_action( 'admin_notices', array( $this, 'admin_notices'));
      // process the custom order meta box order action
      add_action( 'woocommerce_order_action_woocommerce_invoicefox_create_invoice', array( $this, 'process_custom_order_action_invoice' ) );
      add_action( 'woocommerce_order_action_woocommerce_invoicefox_create_proforma', array( $this, 'process_custom_order_action_proforma' ) );
      add_action( 'woocommerce_order_action_woocommerce_invoicefox_create_invt_sale', array( $this, 'process_custom_order_action_invt_sale' ) );
      add_action( 'woocommerce_order_action_woocommerce_invoicefox_check_invt_items', array( $this, 'process_custom_order_action_check_invt_items' ) );
      add_action( 'woocommerce_order_action_woocommerce_invoicefox_mark_invoice_paid', array( $this, 'process_custom_order_action_mark_invoice_paid' ) );
      $this->conf = get_option('woocommerce_invoicefox_settings');
      $translArr = array(0 => "invoice", 1 => "proforma", 2 => "inventory");
      $this->conf['document_to_make'] = $translArr[$this->conf['document_to_make']];
      woocomm_invfox__trace("================ BEGIN ===============");
    }
    /**
     * Initialize the plugin.
     */
    public function init() {
      // Checks if WooCommerce is installed.
      if ( class_exists( 'WC_Integration' ) ) {
	// Include our integration class.
	include_once 'includes/class-wc-integration-invoicefox.php';
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
      if ($notices= get_option('woocommerce_invoicefox_deferred_admin_notices')) {
	foreach ($notices as $notice) {
	  echo "<div class='updated'><p>$notice</p></div>";
	}
	delete_option('woocommerce_invoicefox_deferred_admin_notices');
      }
    }
    /**
     * Add our items for order actions box
     */
    function add_order_meta_box_actions( $actions ) {
      $actions['woocommerce_invoicefox_create_invoice'] = __( $this->conf['app_name'].': Make invoice', $this->text_domain );
      $actions['woocommerce_invoicefox_mark_invoice_paid'] = __( $this->conf['app_name'].': Mark invoice paid', $this->text_domain );
      $actions['woocommerce_invoicefox_create_proforma'] = __( $this->conf['app_name'].': Make proforma', $this->text_domain );
      if ($this->conf['use_inventory'] == "yes") {
	$actions['woocommerce_invoicefox_check_invt_items'] = __( $this->conf['app_name'].': Check inventory', $this->text_domain );
	$actions['woocommerce_invoicefox_create_invt_sale'] = __( $this->conf['app_name'].': Make invent. sale', $this->text_domain );
      }
      return $actions;
    }
    /**
     * Add a new integration to WooCommerce.
     */
    public function add_integration( $integrations ) {
      $integrations[] = 'WC_Integration_InvoiceFox';
      return $integrations;
    }
    function process_custom_order_action_invoice($order) {
      $this->_woocommerce_order_status_completed($order, "invoice");
    }
    function process_custom_order_action_proforma($order) {
      $this->_woocommerce_order_status_completed($order, "proforma");
    }
    function process_custom_order_action_invt_sale($order) {
      $this->_woocommerce_order_status_completed($order, "inventory");
    }
    function process_custom_order_action_mark_invoice_paid($order) {
      $api = new InvfoxAPI($this->conf['api_key'], $this->conf['api_domain'], true);
      $api->setDebugHook("woocomm_invfox__trace");
      $api->markInvoicePaid($order->id);
      $notices= get_option('woocommerce_invoicefox_deferred_admin_notices', array());
      $notices[]= "Marked paid";
      update_option('woocommerce_invoicefox_deferred_admin_notices', $notices);
    }
    function process_custom_order_action_check_invt_items($order) {
      $items = array();
      foreach( $order->get_items() as $item ) {
	if ( 'line_item' == $item['type'] ) {
	  $product = $order->get_product_from_item( $item );
	  $items[] = array('code' => $product->get_sku(), 'qty' => $item['qty']);
	}
      }
      $api = new InvfoxAPI($this->conf['api_key'], $this->conf['api_domain'], true);
      $api->setDebugHook("woocomm_invfox__trace");
      $resp = $api->checkInvtItems($items, $this->conf['from_warehouse_id'], $api->_toSIDate(date('d.m.Y')));
      $msg = "";
      if ($resp) {
	$first = true;
	foreach($resp as $item) {
	  $msg .= ($first?"":", ") . $item['code'] . ": ".
	    ($item['result']==null ? "item code not found" : ( $item['result'] >= 0 ? "OK, on inventory (+ {$item['result']})" : "less inventory ({$item['result']})" ));
	  $first = false;
	}
      }
      $notices= get_option('woocommerce_invoicefox_deferred_admin_notices', array());
      $notices[]= "Inventory items checked: ".$msg;
      update_option('woocommerce_invoicefox_deferred_admin_notices', $notices);
    }
    /**
     * function that collects data and calls invfoxapi functions to create document
     */
    function _woocommerce_order_status_completed( $order, $document_to_make="" ) {
      
      if ($document_to_make) { $this->conf['document_to_make'] = $document_to_make; }
      
      woocomm_invfox__trace("================ BEGIN ===============");

      //$order = new WC_Order( $order_id );
      
      woocomm_invfox__trace($this->conf, "settings");

      woocomm_invfox__trace($order, "ORDER");
      
      $api = new InvfoxAPI($this->conf['api_key'], $this->conf['api_domain'], true);
      $api->setDebugHook("woocomm_invfox__trace");
  
      $vatNum = get_post_meta( $order->id, 'VAT Number', true );


      woocomm_invfox__trace($vatNum, "vatnum");

      $r = $api->assurePartner(array(
				     'name' => $order->billing_first_name." ".$order->billing_last_name.($order->billing_company?", ":"").$order->billing_company,
				     'street' => $order->billing_address_1."\n". $order->billing_address_2,
				     'postal' => $order->billing_postcode,
				     'city' =>$order->billing_city,
				     'country' => $order->billing_country,
				     'vatid' => $vatNum, // TODO -- find where the data is
				     'phone' => $order->billing_phone, //$c->phone.($c->phone_mobile?", ":"").$c->phone_mobile,
				     'website' => "",
				     'email' => $order->billing_email,
				     'notes' => '',
				     'vatbound' => !!$vatNum,//!!$c->vat_number, TODO -- after (2)
				     'custaddr' => '',
				     'payment_period' => $this->conf['customer_general_payment_period'],
				     'street2' => ''
				     ));

      woocomm_invfox__trace("assured partner");
      
      if ($r->isOk()) {
    
	woocomm_invfox__trace("before creating document");
    
	$clientIdA = $r->getData();
	$clientId = $clientIdA[0]['id'];
    
	$date1 = $api->_toSIDate(date('Y-m-d')); //// TODO LONGTERM ... figure out what we do with different Dates on api side (maybe date optionally accepts dbdate format)

	$invid = $this->conf['use_shop_id_for_docnum'] ? str_pad($order->id, 5, "0", STR_PAD_LEFT) : "";
    
	$body2 = array();
	/**/
	foreach( $order->get_items() as $item ) {
	  if ( 'line_item' == $item['type'] ) {
	    woocomm_invfox__trace(".........................................::>>>>");
	    woocomm_invfox__trace($item,0);
	    woocomm_invfox__trace("<<<<::.........................................");
	    $product = $order->get_product_from_item( $item );
	    woocomm_invfox__trace("-----------------------------------------::>>>>");
	    woocomm_invfox__trace($product,0);
	    woocomm_invfox__trace("<<<<::-----------------------------------------");
	    //woocomm_invfox__trace($product,0);
	    //woocomm_invfox__trace($product->get_sku(),0);
	    $body2[] = array(
			     'code' => $product->get_sku(),
			     'title' => $product->post->post_title.($this->conf['add_post_content_in_item_descr']?"\n".$product->post->post_content:""),
			     'qty' => $item['qty'],
			     'mu' => '',
			     'price' => round($item['line_total'] / $item['qty'], $this->conf['round_calculated_netprice_to']),
			     'vat' => round($item['line_tax'] / $item['line_total'] * 100, $this->conf['round_calculated_taxrate_to']),
			     'discount' => 0
			     );
	  }
	}
    
	if ($this->conf['document_to_make'] != 'inventory' && $order->order_shipping > 0) {
	  woocomm_invfox__trace("adding shipping");
	  if ($this->conf['partial_sum_label']) {
	    $body2[] = array(
			     'title' => "= ".$this->conf['partial_sum_label'],
			     'qty' => 1,
			     'mu' => '',
			     'price' => 0,
			     'vat' => 0,
			     'discount' => 0
			     );
	  }

	  $body2[] = array(
			   'title' => $order->shipping_method_title,
			   'qty' => 1,
			   'mu' => '',
			   'price' => $order->order_shipping,
			   'vat' => round($order->order_shipping_tax / $order->order_shipping * 100,  $this->conf['round_calculated_shipping_taxrate_to']),
			   'discount' => 0
			   );
	}
    
	/*      */
	if ($this->conf['document_to_make'] == 'invoice') {
	  woocomm_invfox__trace("before create invoice call");
	  $r2 = $api->createInvoice(
				    array(
					  'title' => $invid,
					  'date_sent' => $date1,
					  'date_to_pay' => $date1,
					  'date_served' => $date1, // MAY NOT BE NULL IN SOME VERSIONS OF USER DBS
					  'id_partner' => $clientId,
					  'taxnum' => '-',
					  'doctype' => 0,
					  'id_document_ext' => $order->id,
					  'pub_notes' => $this->conf['order_num_label'].' #'. $order->id
					  ),
				    $body2
				    );
	  if ($r2->isOk()) {    
	    $invA = $r2->getData();
	    $order->add_order_note("Invoice No. {$invA[0]['title']} was created at {$this->conf['app_name']}.",'woocom-invfox');
	  } 
	  // TODO ... add notification / alert in case of erro
	} elseif ($this->conf['document_to_make'] == 'proforma') {
	  woocomm_invfox__trace("before create proforma call");
	  $r2 = $api->createProFormaInvoice(
					    array(
						  'title' => "",
						  'date_sent' => $date1,
						  'days_valid' => $this->conf['proforma_days_valid'],
						  'id_partner' => $clientId,
						  'taxnum' => '-',
						  'pub_notes' => $this->conf['order_num_label'].' #'. $order->id
						  ),
					    $body2
					    );
	  if ($r2->isOk()) {    
	    $invA = $r2->getData();
	    $order->add_order_note("ProForma Invoice No. {$invA[0]['title']} was created at {$this->conf['app_name']}.",'woocom-invfox');
	  } 
	  // TODO ... add notification / alert in case of erro
	} elseif ($this->conf['document_to_make'] == 'inventory') {
	  woocomm_invfox__trace("before create inventory sale call");
	  $r2 = $api->createInventorySale(
					  array(
						'title' => $invid,
						'date_created' => $date1,
						'id_contact_to' => $clientId,
						'id_contact_from' => $this->conf['from_warehouse_id'],
						'taxnum' => '-',
						'doctype' => 1,
						'pub_notes' => $this->conf['order_num_label'].' #'. $order->id
						),
					  $body2
					  );
	  if ($r2->isOk()) {    
	    $invA = $r2->getData();
	    $order->add_order_note("Inventory sales document No. {$invA[0]['title']} was created at {$this->conf['app_name']}.",'woocom-invfox');
	  } 
	}
	woocomm_invfox__trace($r2);	
	woocomm_invfox__trace("after create invoice");	
      }
  
      return true;
    }
  }
  $WC_InvoiceFox = new WC_InvoiceFox( __FILE__ );
}
?>
