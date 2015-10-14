<?php
/**
 * InvoiceFox Integration.
 *
 * @package  WC_Integration_InvoiceFox
 * @category Integration
 * @author   Janko M.
 */
if ( ! class_exists( 'WC_Integration_InvoiceFox' ) ) :

  class WC_Integration_InvoiceFox extends WC_Integration {
	
	/**
	 * Init and hook in the integration.
	 */
	
	public function __construct() 
	{
	  global $woocommerce;
	  $this->id                 = 'invoicefox';
	  $this->method_title       = __( 'InvoiceFox', 'woocommerce-invoicefox' );
	  $this->method_description = __( 'An integration that connects WooCommerce to InvoiceFox for invoicing and optionally inventory.', 'woocommerce-invoicefox' );
	  // Load the settings.
	  $this->init_form_fields();
	  $this->init_settings();
	  // Define user set variables.
	  $this->api_key = $this->get_option( 'api_key' );
	  $this->api_domain = $this->get_option( 'api_domain' );
	  $this->app_name = $this->get_option( 'app_name' );
	  $this->document_to_make = $this->get_option( 'document_to_make' );
	  $this->proforma_days_valid = $this->get_option( 'proforma_days_valid' );
	  $this->customer_general_payment_period = $this->get_option( 'customer_general_payment_period' );
	  $this->add_post_content_in_item_descr = $this->get_option( 'add_post_content_in_item_descr' );
	  $this->partial_sum_label = $this->get_option( 'partial_sum_label' );
	  $this->round_calculated_taxrate_to = $this->get_option( 'round_calculated_taxrate_to' );
	  $this->round_calculated_netprice_to = $this->get_option( 'round_calculated_netprice_to' );
	  $this->from_warehouse_id = $this->get_option( 'from_warehouse_id' );
	  $this->debug            = $this->get_option( 'debug' );
	  /*
			       'API_KEY'=>"s0******************************cdkw", // you get it in InvoiceFox/Cebelca/Abelie/..., on page "access" after you activate the API
			       'API_DOMAIN'=>"www.cebelca.biz", // options: "www.invoicefox.com" "www.invoicefox.co.uk" "www.invoicefox.com.au" "www.cebelca.biz" "www.abelie.biz" 
			       'APP_NAME'=>"Cebelca.biz",
			       'document_to_make'=>"inventory", // options: "invoice" "proforma" "inventory"
			       'proforma_days_valid'=>10,
			       'customer_general_payment_period'=>5,
			       'add_post_content_in_item_descr'=>false,
			       'partial_sum_label'=>'Skupaj', // Empty for no partial sum line
			       'round_calculated_taxrate_to'=>1,
			       'round_calculated_netprice_to'=>3,
			       'from_warehouse_id'=>22
	  */
	  // Actions.
	  add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize integration settings form fields.
	 */

	public function init_form_fields() 
	{
	  $this->form_fields = 
		array(
			  'api_key' => array(
								 'title'             => __( 'API Key', 'woocommerce-integration-demo' ),
								 'type'              => 'text',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'api_domain' => array(
								 'title'             => __( 'API Domain', 'woocommerce-integration-demo' ),
								 'type'              => 'text',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'app_name' => array(
								 'title'             => __( 'Application Name', 'woocommerce-integration-demo' ),
								 'type'              => 'text',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'document_to_make' => array(
								 'title'             => __( 'Document type to generate', 'woocommerce-integration-demo' ),
								 'type'              => 'select',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'proforma_days_valid' => array(
								 'title'             => __( 'Validity of proforma invoice', 'woocommerce-integration-demo' ),
								 'type'              => 'decimal',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'customer_general_payment_period' => array(
								 'title'             => __( 'Payment period for customer', 'woocommerce-integration-demo' ),
								 'type'              => 'decimal',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'add_post_content_in_item_descr' => array(
								 'title'             => __( 'Add post content in description', 'woocommerce-integration-demo' ),
								 'type'              => 'checkbox',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'partial_sum_label' => array(
								 'title'             => __( 'Partial SUM label', 'woocommerce-integration-demo' ),
								 'type'              => 'text',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'round_calculated_taxrate_to' => array(
								 'title'             => __( 'Round taxrate to', 'woocommerce-integration-demo' ),
								 'type'              => 'decimal',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'round_calculated_netprice_to' => array(
								 'title'             => __( 'Round netprice to', 'woocommerce-integration-demo' ),
								 'type'              => 'decimal',
								 'description'       => __( 'Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo' ),
								 'desc_tip'          => true,
								 'default'           => ''
								 ),
			  'from_warehouse_id' => array(
							   'title'             => __( 'Wrehouse ID', 'woocommerce-integration-demo' ),
							   'type'              => 'decimal',
							   'label'             => __( 'Enable logging', 'woocommerce-integration-demo' ),
							   'default'           => 'no',
							   'description'       => __( 'Log events such as API requests', 'woocommerce-integration-demo' ),
							   ),
			  'debug' => array(
							   'title'             => __( 'Debug Log', 'woocommerce-integration-demo' ),
							   'type'              => 'checkbox',
							   'label'             => __( 'Enable logging', 'woocommerce-integration-demo' ),
							   'default'           => 'no',
							   'description'       => __( 'Log events such as API requests', 'woocommerce-integration-demo' ),
							   ),
			  );
	}
  }

endif;
?>