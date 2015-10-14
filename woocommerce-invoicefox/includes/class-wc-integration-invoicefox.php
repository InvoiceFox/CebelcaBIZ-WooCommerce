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
      $this->api_key = $this->get_option( 'api_key' ) ;
      $this->api_domain = $this->get_option( 'api_domain', "www.cebelca.biz");
      $this->app_name = $this->get_option( 'app_name', "Cebelca.biz");
      $this->use_shop_id_for_docnum = $this->get_option( 'use_shop_id_for_docnum', false ); //*
      $this->use_inventory = $this->get_option( 'use_inventory');
      $this->proforma_days_valid = $this->get_option( 'proforma_days_valid', "10" );
      $this->customer_general_payment_period = $this->get_option( 'customer_general_payment_period', "5" );
      $this->add_post_content_in_item_descr = $this->get_option( 'add_post_content_in_item_descr', false );
      $this->partial_sum_label = $this->get_option( 'partial_sum_label', "Seštevek" );
      $this->order_num_label = $this->get_option( 'order_num_label', "Na osnovi naročila:" ); //*
      $this->round_calculated_taxrate_to = $this->get_option( 'round_calculated_taxrate_to', 1 );
      $this->round_calculated_netprice_to = $this->get_option( 'round_calculated_netprice_to', 4 );
      $this->round_calculated_shipping_taxrate_to = $this->get_option( 'round_calculated_shipping_taxrate_to', 0); //*
      $this->from_warehouse_id = $this->get_option( 'from_warehouse_id', 0 );
      $this->debug            = $this->get_option( 'debug' );
      // Actions.
      add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Initialize integration settings form fields.
     */


    /**
     * Generate Button HTML.
     */
    public function generate_button_html( $key, $data ) {
      $field    = $this->plugin_id . $this->id . '_' . $key;
      $defaults = array(
			'class' => 'button-secondary',
			'css' => '',
			'custom_attributes' => array(),
			'desc_tip' => false,
			'description' => '',
			'title' => '',
			);
      $data = wp_parse_args( $data, $defaults );
      ob_start();
			
      echo "<tr valign='top'>
	    <th scope='row' class='titledesc'>
            <label for='". esc_attr( $field ) ."'>".wp_kses_post( $data['title'] )."</label>
            <!-- $ this - >get_tooltip_html( $data )-->
            </th>
            <td class='forminp'>
	    <fieldset>
	     <legend class='screen-reader-text'><span>".wp_kses_post( $data['title'] )."</span></legend>
	      <button class='". esc_attr( $data['class'] ). "' type='button' name='". esc_attr( $field ) ."' id='". esc_attr( $field ) ."' 
               style='". esc_attr( $data['css'] ). "' ".">". wp_kses_post( $data['title'] )."</button>
	     <!-- . $ this - >get_description_html( $data ) -->
	    </fieldset>
	   </td>
	  </tr>";
      // $ this - > get_custom_attribute_html( $data ).
      return ob_get_clean();
    }
		
    public function init_form_fields() 
    {
      $this->form_fields = 
	array(
	      'api_key' => 
	      array(
		    'title'             => __( 'API Key', 'woocommerce-integration-demo' ),
		    'type'              => 'password',
		    'description'       => __( 'Enter your API Key. You can find this in "Access" page, API Keys.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'api_domain' => 
	      array(
		    'title'             => __( 'API Domain', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Enter the domain of web application (www.cebelca.biz, www.invoicefox.com, ...)', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      /*	      'customize_button' => 
	      array(
		    'title'             => __( 'Check API', 'woocommerce-integration-demo' ),
		    'type'              => 'button',
		    'custom_attributes' => array(
						 'onclick' => "location.href='http://www.woothemes.com'",
						 ),
		    'description'       => __( 'Customize your settings by going to the integration site directly.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    ),*/
	      'app_name' => 
	      array(
		    'title'             => __( 'Application Name', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Name of application. Used for messages.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'use_shop_id_for_docnum' => 
	      array(
		    'title'             => __( 'Document numbers', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Use WooCommerce ID-s for document numbers', 'woocommerce-integration-demo' ),
		    'description'       => __( 'Do you want the Invoicefox/Cebelca to determine the document number or WooCommece.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'add_post_content_in_item_descr' => 
	      array(
		    'title'             => __( 'Item description', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Add post content to item description', 'woocommerce-integration-demo' ),
		    'description'       => __( '', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'proforma_days_valid' => 
	      array(
		    'title'             => __( 'Validity of proforma invoice', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'How many days are the created Proforma Invoices valid.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'customer_general_payment_period' => 
	      array(
		    'title'             => __( 'Payment period for customer', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'What is the general payment period for the customers that are created.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'order_num_label' => 
	      array(
		    'title'             => __( 'Order number label', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'The text that describes the order number.', 'woocommerce-integration-demo' ),
		    'default'           => '',
		    ),
	      'partial_sum_label' => 
	      array(
		    'title'             => __( 'Partial SUM label', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( '', 'woocommerce-integration-demo' ),
		    'default'           => '',
		    'description'       => __( 'Leave empty for no partial row SUM.', 'woocommerce-integration-demo' ),
		    ),
	      'round_calculated_netprice_to' => 
	      array(
		    'title'             => __( 'Round netprice to', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'To how many digits do we need to round the NET price. 4 is good default.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'round_calculated_taxrate_to' => 
	      array(
		    'title'             => __( 'Round item taxrate to', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'To how many digits do we need to round when calculating tax rate. It&#39;s usually 1 or 0.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'round_calculated_shipping_taxrate_to' => 
	      array(
		    'title'             => __( 'Round shipping taxrate to', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'To how many digits do we need to calculate stipping tax rate. Look at online help (at github) for explanation.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'use_inventory' => 
	      array(
		    'title'             => __( 'Inventory module', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Enable inventory functionality', 'woocommerce-integration-demo' ),
		    'description'       => __( '', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => '',
		    ),
	      'from_warehouse_id' => 
	      array(
		    'title'             => __( 'Warehouse ID', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'default'           => '',
		    'desc_tip'          => true,
		    'description'       => __( 'The ID of the warehouse in InvoiceFox from where inventory is sold. Look at github instructions on how to get it.', 'woocommerce-integration-demo' ),
		    ),
	      /*	      'debug' => 
			      array(
			      'title'             => __( 'Debug Log', 'woocommerce-integration-demo' ),
			      'type'              => 'checkbox',
			      'label'             => __( 'Enable logging', 'woocommerce-integration-demo' ),
			      'default'           => 'no',
			      'description'       => __( 'Log events such as API requests', 'woocommerce-integration-demo' ),
			      ),*/
	      );
    }
  }

endif;
?>