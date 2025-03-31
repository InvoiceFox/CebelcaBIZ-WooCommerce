<?php
/**
 * Cebelca BIZ Plugin.
 *
 * @package  WC_Integration_Cebelcabiz
 * @category Integration
 * @author   Janko M.
 */
if ( ! class_exists( 'WC_Integration_Cebelcabiz' ) && class_exists( 'WC_Integration' ) ) :

  class WC_Integration_Cebelcabiz extends WC_Integration {
		
    /**
     * Init and hook in the integration.
     */
		
    public function __construct() 
    {
      global $woocommerce;
      $this->id                 = 'cebelcabiz';
      $this->method_title       = __( 'Čebelca BIZ', 'cebelcabiz' );
      $this->method_description = __( 'Dodatek za WooCommerce, ki omogoča avtomatsko kreiranje računov v Čebelci BIZ, davčno potrjevanje računov, odpis zaloge. Dodatek je prosto dostopen in je na voljo "kot je". Navodila najdete na <a href="https://www.cebelca.biz/navodila/woocommerce/">strani Čebelca BIZ</a>', 'cebelcabiz' );
      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();
      // Define user set variables.
      $this->api_key = $this->get_option( 'api_key', "" ) ;
      $this->api_domain = $this->get_option( 'api_domain', "www.cebelca.biz" );
      $this->app_name = $this->get_option( 'app_name', "Cebelca BIZ" );
      $this->use_shop_id_for_docnum = $this->get_option( 'use_shop_id_for_docnum', false ); //*
      $this->add_sku_to_line = $this->get_option( 'add_sku_to_line' );
      $this->proforma_days_valid = $this->get_option( 'proforma_days_valid', "10" );
      $this->customer_general_payment_period = $this->get_option( 'customer_general_payment_period', "5" );
      $this->add_post_content_in_item_descr = $this->get_option( 'add_post_content_in_item_descr', false );
      $this->partial_sum_label = $this->get_option( 'partial_sum_label', "Seštevek" );
      $this->order_num_label = $this->get_option( 'order_num_label', "Na osnovi naročila:" ); //*
      $this->round_calculated_taxrate_to = $this->get_option( 'round_calculated_taxrate_to', 1 );
      $this->round_calculated_netprice_to = $this->get_option( 'round_calculated_netprice_to', 4 );
      $this->round_calculated_shipping_taxrate_to = $this->get_option( 'round_calculated_shipping_taxrate_to', 0); //*
      $this->from_warehouse_id = $this->get_option( 'from_warehouse_id', 0 );
      $this->debug_mode       = $this->get_option( 'debug_mode', 'yes' );
      // new options below -- TODO ... can we group them together in interface to make it more clear?
      $this->order_actions_enabled = $this->get_option( 'order_actions_enabled' );
      $this->on_order_on_hold = $this->get_option( 'on_order_on_hold' );
      $this->on_order_processing = $this->get_option( 'on_order_processing' );
      $this->on_order_completed = $this->get_option( 'on_order_completed' );
      $this->fiscal_mode = $this->get_option( 'fiscal_mode' );
      $this->fiscal_test_mode = $this->get_option( 'fiscal_test_mode' );
      $this->fiscal_id_location = $this->get_option( 'fiscal_id_location' );
      $this->fiscal_op_tax_id = $this->get_option( 'fiscal_op_tax_id' );
      $this->fiscal_op_name = $this->get_option( 'fiscal_op_name' );
      $this->vat_levels_list = $this->get_option( 'vat_levels_list', "0, 5, 9.5, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27" );
      $this->payment_methods_map = $this->get_option( 'payment_methods_map', "PayPal->PayPal;Gotovina->Gotovina" );
      $this->fiscalize_payment_methods = $this->get_option( 'fiscalize_payment_methods', "* - vsi" );
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
		    'title'             => __( 'Skrivni API ključ', 'woocommerce-integration-demo' ),
		    'type'              => 'password',
		    'description'       => __( 'API ključ dobite, ko da aktivirate API na Čebelca BIZ, na strani Nastavitve &gt; Dostop.', 'woocommerce-integration-demo' ),
		    // 'desc_tip'          => true,
		    'default'           => ''
		    ),
	      /* 'api_domain' =>
          // remove this
	      array(
		    'title'             => __( 'Domena storitve', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Enter the domain of web application (www.cebelca.biz, www.invoicefox.com, ...)', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),*/
          // remove this
	      /*'app_name' => 
	      array(
		    'title'             => __( 'Naziv aplikacije', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Name of application. Used for messages.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
          // remove this
	      'use_shop_id_for_docnum' => 
	      array(
		    'title'             => __( 'Document numbers', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Use WooCommerce ID-s for document numbers', 'woocommerce-integration-demo' ),
		    'description'       => __( 'Do you want the Invoicefox/Cebelca to determine the document number or WooCommece.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => ''
		    ),
          */

          'section_various' => array(
              'title' => __( 'Detajli delovanja', 'woocommerce-integration-demo' ),
              'type' => 'title',
              'description' => __( 'Naslednje nastavitve lahko v večini primerov oz. vsaj na začetku pustite kot so. Po potrebi pa jih seveda lahko spremenite.', 'woocommerce-integration-demo' ),
          ),
          
          'debug_mode' => 
	      array(
		    'title'             => __( 'Beleženje dogodkov (debug)', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Aktiviraj beleženje dogodkov', 'woocommerce-integration-demo' ),
		    'description'       => __( 'Aktivira beleženje dogodkov v dnevnik za lažje odkrivanje napak. Dnevnik se nahaja v: ' . WP_CONTENT_DIR . '/cebelcabiz-debug.log', 'woocommerce-integration-demo' ),
		    'default'           => 'yes',
		    ),
          
          
          'add_post_content_in_item_descr' => 
	      array(
		    'title'             => __( 'Opis izdelkov', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Dodaj daljši opis artikla v sam račun', 'woocommerce-integration-demo' ),
		    'description'       => __( '', 'woocommerce-integration-demo' ),
		    //'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'proforma_days_valid' => 
	      array(
		    'title'             => __( 'Veljavnost predračuna (opcijsko)', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'Če se iz plugina kreirajo predračuni, kako dolgo naj imajo veljavnost.', 'woocommerce-integration-demo' ),
		    //'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'customer_general_payment_period' => 
	      array(
		    'title'             => __( 'Rok plačila pri stranki', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'Na koliko dni naj bo nastavljen rok plačila pri strankah, ki jih plugin doda v Čebelco.', 'woocommerce-integration-demo' ),
		    //'desc_tip'          => true,
		    'default'           => ''
		    ),
	      'order_num_label' => 
	      array(
		    'title'             => __( 'Besedilo o št. naročila', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Besedilo pred številko naročila, ki se doda v besedilo nad tabelo pri računu. Če je to besedilo prazno se tudi št. naročila ne bo dodala.', 'woocommerce-integration-demo' ),
		    'default'           => '',
		    ),
	      'partial_sum_label' => 
	      array(
		    'title'             => __( 'Besedilo za delni seštevek', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'default'           => '',
		    'description'       => __( 'Če želite da program na računu prikaže seštevek artiklov preden doda stroške dostave vnesite besedilo vrstice.', 'woocommerce-integration-demo' ),
		    ),
	      'round_calculated_netprice_to' => 
	      array(
		    'title'             => __( 'Zaokrožanje neto cene', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'Na koliko decimalk naj se zaokroži neto (brez DDV) cena pri prenosu v Čebelco. 4 je dobra začetna vrednost.', 'woocommerce-integration-demo' ),
            'default'           => '4'
		    //'desc_tip'          => true,
		    ),
	      'round_calculated_taxrate_to' => 
	      array(
		    'title'             => __( 'Zaokrožane davčne stopnje', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'Na koliko decimalk naj se zaokroži davčna stopnja, ki se mora izračunati. Običajno 1.', 'woocommerce-integration-demo' ),
		    'default'           => '1'
		    ),
	      'round_calculated_shipping_taxrate_to' => 
	      array(
		    'title'             => __( 'Zaokrožanje zneska dostave', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'description'       => __( 'Na koliko decimalk naj se zaokroži znesek dostave. Običajno 1.', 'woocommerce-integration-demo' ),
		    'default'           => '2'
		    ),
	      'add_sku_to_line' => 
	      array(
		    'title'             => __( 'Naj se postavkam doda SKU', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'SKU je enaka šifri artikla v Čebelco. Če želite s temi nakupi tudi odpisovati zalogo, naj se doda.', 'woocommerce-integration-demo' ),
		    'description'       => __( '', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => '',
		    ),
	      'from_warehouse_id' => 
	      array(
		    'title'             => __( 'ID Skladišča', 'woocommerce-integration-demo' ),
		    'type'              => 'decimal',
		    'default'           => '',
		    'description'       => __( 'Če vodite zalogo in imate nastavljeno, da se avtomatsko kreirajo dobavnice vpišite ID skladišča iz katerega naj se zaloga odpiše.', 'woocommerce-integration-demo' ),
		    ),
	      'vat_levels_list' => 
	      array(
		    'title'             => __( 'Možne davčne stopnje', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'default'           => '',
		    'description'       => __( 'Tu v obliki seznama kjer so vrednosti ločene z vejico, decimalka pa uporablja piko naštejete vse davčne stopnje, ki so možne v vaši trgovini.', 'woocommerce-integration-demo' ),
		    ),
	      'payment_methods_map' => 
	      array(
		    'title'             => __( 'Pretvorba načinov plačila', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'default'           => '',
		    'description'       => __( 'Tu v obliki "Način plačila woocomerce->Način plačila Čebelca;...." vnesete kako se naj načini plačila v vašem Woocommerce pretvorijo v načina plačila na Čebelci', 'woocommerce-integration-demo' ),
		    ),

          'section_actions' => array(
              'title' => __( 'Kreiranje računov', 'woocommerce-integration-demo' ),
              'type' => 'title',
              'description' => __( 'Računi se kreirajo ob spremembi statusa naročila na določeni status. Najbolj pogosto je to status "Zaključeno". Račun naj se kreira le ob enem od statusov, ne vseh, drugače bo prišlo do podvajanja.<br/>Za začetek je dobro izbrati "Ustvari osnutek" pri statusu "Zaključeno".', 'woocommerce-integration-demo' ),
          ),

          'on_order_on_hold' => 
          array(
                'title'         => __( 'Akcija ob spremembi na "Zadržano"', 'woocommerce-integration-demo' ),
                'type'          => 'select',
                'class'         => array( 'wps-drop' ),
                'label'         => __( 'Delivery options' ),
                'options'       => array(
                                         'blank'=> __( 'Brez akcije', 'wps' ),
                                         'create_proforma'=> __( 'Ustvari predračun', 'wps' ),
                                         'create_proforma_email'=> __( 'Ustvari predračun, pošlji PDF po e-pošti', 'wps' ),
                                         'create_invoice_draft'=> __( 'Ustvari osnutek računa', 'wps' ),
                                         )
                ),

          'on_order_processing' => 
          array(
                'title'         => __( 'Akcija ob spremembi na "V&nbsp;obdelavi"', 'woocommerce-integration-demo' ),
                'type'          => 'select',
                'class'         => array( 'wps-drop' ),
                'label'         => __( 'Delivery options' ),
                'options'       => array(
                                         'blank'=> __( 'Brez akcije', 'wps' ),
                                         'create_proforma'=> __( 'Ustvari predračun', 'wps' ),
                                         'create_proforma_email'=> __( 'Ustvari predračun, pošlji PDF po e-pošti', 'wps' ),
                                         'create_invoice_draft'=> __( 'Ustvari osnutek računa', 'wps' ),
                                         )
                ),

          'on_order_completed' => 
          array(
                'title'         => __( 'Akcija ob spremembi na "Zaključeno"', 'woocommerce-integration-demo' ),
                'type'          => 'select',
                'class'         => array( 'wps-drop' ),
                'label'         => __( 'Delivery options' ),
                'options'       => array(
                                         'blank'=> __( 'Brez akcije', 'wps' ),
                                         'create_proforma'=> __( 'Ustvari predračun', 'wps' ),
                                         'create_proforma_email'=> __( 'Ustvari predračun, pošlji PDF po e-pošti', 'wps' ),
                                         'create_invoice_draft'=> __( 'Ustvari osnutek računa', 'wps' ),
                                         'create_invoice_complete'=> __( 'Ustvari in izdaj račun', 'wps' ),
                                         'create_invoice_complete_email'=> __( 'Ustvari in izdaj račun, pošlji PDF po e-pošti', 'wps' ),
                                         'create_invoice_complete_paid_email'=> __( 'Ustvari in izdaj račun, označi plačano, pošlji PDF', 'wps' ),
                                         'create_invoice_complete_paid_inventory_email'=> __( 'Ustvari in izdaj račun, označi plačano, odpiši zalogo, pošlji PDF', 'wps' ),
                                         'email'=> __( 'Just email PDF', 'wps' ),
                                         )
          ),         
          
	      'order_actions_enabled' => 
	      array(
		    'title'             => __( 'Akcije pri naročilu', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Omogoči Akcije pri naročilu - to je stari način uporabe, predlagano je da ne obkljukate.', 'woocommerce-integration-demo' ),
            'default'           => '',
		    ),

          
          'section_fiscal' => array(
              'title' => __( 'Davčna blagajna', 'woocommerce-integration-demo' ),
              'type' => 'title',
              'description' => __( 'Nastavitve v povezavi z davčnim potrjevanjem računov. Če vam plugin kreira le osnutke in račun izdate ter tudi potrdite v sami Čebelci, kot to priporočamo na začetku, potem ta polja ni potrebno izpolniti. Davčno potrjevanje je v Slo. potrebno pri vseh računih ki niso plačani preko direktnega bančnega nakazila ali PayPal.<br/></br/>Preden vklopite avtomatsko davčno potrjevanje se prepričate da imate Wocoommerce in samo davčno blagajno pravilno nastavljeno in da se računi kreirajo pravilno (posebej pozorni bodite na davke in poštnino)', 'woocommerce-integration-demo' ),
          ),
          
	      'fiscal_mode' => 
	      array(
              'title'             => __( 'Davčno potrjevanje računov', 'woocommerce-integration-demo' ),
              'type'              => 'checkbox',
              'label'             => __( 'Aktiviraj davčno potrjevanje', 'woocommerce-integration-demo' ),
              'description'       => __( 'Če imate zgoraj nastavljeno, naj se računi tudi izdajo in so ti "gotovinski" (po FURS) potem aktivirajte sledeče. Račune lahko davčno potrjujete tudi sami v Čebelci.', 'woocommerce-integration-demo' ),
              'desc_tip'          => true,
              'default'           => '',
		    ),
          // remove
	      /*'fiscal_test_mode' => 
	      array(
		    'title'             => __( 'Fiscalisation test mode', 'woocommerce-integration-demo' ),
		    'type'              => 'checkbox',
		    'label'             => __( 'Activate test mode', 'woocommerce-integration-demo' ),
		    'description'       => __( 'Invoices are fiscalized to TEST FURS server. Meant for developers, so only use on a separate (only for testing) account. Don\'t activate on non test / non developer Cebelca.biz account.', 'woocommerce-integration-demo' ),
		    'desc_tip'          => true,
		    'default'           => '',
		    ),*/
	      'fiscalize_payment_methods' => 
	      array(
		    'title'             => __( 'Načini plačila kjer dav. potrdi', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Tu lahko omejite pri katerih načinih plačila, naj se račun davčno potrdi. Besedilo se mora točno ujemati, ločite z vejicami. Če je vnešena * potem se bo potrjevalo pri vseh.', 'woocommerce-integration-demo' ),
		    'default'           => '',
            //		    'description'       => __( 'Required if fiscalisation activated.', 'woocommerce-integration-demo' ),
		    ),
	      'fiscal_id_location' =>
          array(
		    'title'             => __( 'ID prostora in blagajne', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'description'       => __( 'Zahtevan podatek, če imate aktivirano avtom. davčno potrjevanje. Številski podatek, najdete ga na strani Podatki & ID-ji (na dnu).', 'woocommerce-integration-demo' ),
		    'default'           => '',
            //		    'description'       => __( 'Required if fiscalisation activated.', 'woocommerce-integration-demo' ),
		    ),
	      'fiscal_op_tax_id' => 
	      array(
		    'title'             => __( 'Osebna davčna številka izdajatelja', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'default'           => '',
		    'description'       => __( 'Zahtevan podatek, če imate aktivirano avtom. davčno potrjevanje. Davčna številka <b>osebe</b>, ki račune izdaja (spreminja statuse).', 'woocommerce-integration-demo' ),
		    ),
	      'fiscal_op_name' => 
	      array(
		    'title'             => __( 'Osebni naziv izdajatelja', 'woocommerce-integration-demo' ),
		    'type'              => 'text',
		    'default'           => '',
		    'description'       => __( 'Zahtevan podatek, če imate aktivirano avtom. davčno potrjevanje. Naziv. npr. samo ime <b>osebe</b>, ki račune izdaja (spreminja statuse).', 'woocommerce-integration-demo' ),
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


//      $this->finalize_invoices = $this->get_option( 'finalize_invoices' );


endif;
?>
