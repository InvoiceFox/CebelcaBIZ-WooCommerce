<?php
/*
  Plugin Name: WooCommerce+InvoiceFox
*/

// Status: Alpha

// THE CONFIGURATION

$woocom_invfox__config = array(
			       'API_KEY'=>"ENTER-YOUR-API-KEY", // you get it in InvoiceFox, on page "access" after you activate the API
			       'API_DOMAIN'=>"www.cebelca.biz", // options: "www.invoicefox.com" "www.invoicefox.co.uk" "www.invoicefox.com.au" "www.cebelca.biz" "www.abelie.biz" 
			       'APP_NAME'=>"Cebelca.biz",
			       'make_invoice_or_proforma'=>"proforma", // options: "invoice" "proforma"
			       'proforma_days_valid'=>10,
			       'customer_general_payment_period'=>5,
			       'add_post_content_in_item_descr'=>false
			       );

// END OF THE CONFIGURATION

//ini_set('display_errors', 1);
//error_reporting(E_ALL ^ E_NOTICE);
require_once(dirname(__FILE__).'/lib/invfoxapi.php');
require_once(dirname(__FILE__).'/lib/strpcapi.php');

function woocomm_invfox__trace($x) { error_log(print_r($x,true)."-----"); }
 
function woocomm_invfox__woocommerce_order_status_completed( $order_id ) {

  global $woocom_invfox__config; $CONF = $woocom_invfox__config;

  woocomm_invfox__trace("============ INVFOX::begin ===========");

  $order = new WC_Order( $order_id );

  woocomm_invfox__trace($order,0);
  
  $api = new InvfoxAPI($CONF['API_KEY'], $CONF['API_DOMAIN'], true);
  $api->setDebugHook("woocomm_invfox__trace");
  
  $r = $api->assurePartner(array(
				 'name' => $order->billing_first_name." ".$order->billing_last_name.($order->billing_company?", ":"").$order->billing_company,
				 'street' => $order->billing_address_1."\n". $order->billing_address_2,
				 'postal' => $order->billing_postcode,
				 'city' =>$order->billing_city,
				 'country' => $order->billing_country,
				 'vatid' => "", // TODO -- find where the data is
				 'phone' => $order->billing_phone, //$c->phone.($c->phone_mobile?", ":"").$c->phone_mobile,
				 'website' => "",
				 'email' => $order->billing_email,
				 'notes' => '',
				 'vatbound' => false,//!!$c->vat_number, TODO -- after (2)
				 'custaddr' => '',
				 'payment_period' => $CONF['customer_general_payment_period'],
				 'street2' => ''
				 ));

  woocomm_invfox__trace("============ INVFOX::assured partner ============");
      
  if ($r->isOk()) {
    
    woocomm_invfox__trace("============ INVFOX::before create invoice ============");
    
    $clientIdA = $r->getData();
    $clientId = $clientIdA[0]['id'];
    
    $date1 = $api->_toSIDate(date('Y-m-d')); //// TODO LONGTERM ... figure out what we do with different Dates on api side (maybe date optionally accepts dbdate format)
    $invid = str_pad($order_id, 5, "0", STR_PAD_LEFT); //todo: ask, check
    
    $body2 = array();
    /**/
    foreach( $order->get_items() as $item ) {
      if ( 'line_item' == $item['type'] ) {
	woocomm_invfox__trace($item,0);
	$product = $order->get_product_from_item( $item );
	$body2[] = array(
			 'title' => $product->post->post_title.($CONF['add_post_content_in_item_descr']?"\n".$product->post->post_content:""),
			 'qty' => $item['qty'],
			 'mu' => '',
			 'price' => round($item['line_total'] / $item['qty'], 2),
			 'vat' => round($item['line_tax'] / $item['line_total'] * 100, 2),
			 'discount' => 0
			 );
      }
    }
    /*      */
    woocomm_invfox__trace("============ INVFOX::before create invoice call ============");
    if ($CONF['make_invoice_or_proforma'] == 'invoice') {
      $r2 = $api->createInvoice(
				array(
				      'title' => $invid,
				      'date_sent' => $date1,
				      'date_to_pay' => $date1,
				      'date_served' => $date1, // MAY NOT BE NULL IN SOME VERSIONS OF USER DBS
				      'id_partner' => $clientId,
				      'taxnum' => '-'
				      ),
				$body2
				);
      if ($r2->isOk()) {    
	$invA = $r2->getData();
	$order->add_order_note("Invoice No. {$invA[0]['title']} was created at {$CONF['APP_NAME']}.",'woocom-invfox');
      } 
      // TODO ... add notification / alert in case of erro
    } elseif ($CONF['make_invoice_or_proforma'] == 'proforma') {
      $r2 = $api->createProFormaInvoice(
					array(
					      'title' => $invid,
					      'date_sent' => $date1,
					      'days_valid' => $CONF['proforma_days_valid'],
					      'id_partner' => $clientId,
					      'taxnum' => '-'
					      ),
					$body2
					);
      if ($r2->isOk()) {    
	$invA = $r2->getData();
	$order->add_order_note("ProForma Invoice No. {$invA[0]['title']} was created at {$CONF['APP_NAME']}.",'woocom-invfox');
      } 
      // TODO ... add notification / alert in case of erro
    }
    woocomm_invfox__trace($r2);	
    woocomm_invfox__trace("============ INVFOX::after create invoice ============");	
  }
    
  return true;
}



add_action( 'woocommerce_order_status_completed',
	    'woocomm_invfox__woocommerce_order_status_completed');

?>
