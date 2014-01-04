<?php
/*
  Plugin Name: WooCommerce+InvoiceFox
*/


// THE CONFIGURATION

$woocom_invfox__config = array(
			       'API_KEY'=>"db8jtivpq1c190g6783erow6s7fh4vf5cuyandtk",
			       'API_DOMAIN'=>"www.cebelca.biz"
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

  $order->add_order_note('The invoice is being made in invoicefox.','woocom-invfox');

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
				 'email' => $order->billing_email, //$c->, // get email address, probalbly in some other table
				 'notes' => '',
				 'vatbound' => false,//!!$c->vat_number, TODO -- after (2)
				 'custaddr' => '',
				 'payment_period' => 5, //todo: make configurable
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
			 'title' => $product->post->post_title."\n".$product->post->post_content,
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
    woocomm_invfox__trace("============ INVFOX::after create invoice ============");	
  }
  
  $order->add_order_note('Invoice in InvoiceFox was created.','woocom-invfox');
  
  return true;
}
  


add_action( 'woocommerce_order_status_completed',
	    'woocomm_invfox__woocommerce_order_status_completed');

?>