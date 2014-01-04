<?php

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);

require_once(dirname(__FILE__).'/lib/invfoxapi.php');
require_once(dirname(__FILE__).'/lib/strpcapi.php');

class InvFox extends Module
{
  private $_html = '';
  private $_postErrors = array();

  function __construct()
  {
    $version_mask = explode('.', _PS_VERSION_, 3);
    $version_test = $version_mask[0] > 0 && $version_mask[1] > 3;
    $this->name = 'invfox';
    $this->tab = $version_test ? 'others' : 'eCartService.net Tutorials';
    if ($version_test)
      $this->author = 'eCartService.net';
    $this->version = '0.1.0';
    parent::__construct();
    $this->displayName = $this->l('InvoiceFox module');
    $this->description = $this->l('InvoiceFox module - fast invoicing.');
  }
  public function install()
  {
    Configuration::updateValue('INVFOX_DELIVERED_ID', 5);
    Configuration::updateValue('INVFOX_API_KEY', "3zkm7q5c2wbrcgl07j1rh13sa8tu6oifednes8ax");
    Configuration::updateValue('INVFOX_API_DOMAIN', "www.invoicefox.com");
    parent::install();
    if (!$this->registerHook('updateOrderStatus'))
      return false;
    if (!$this->registerHook('displayAdminOrder'))
      return false;
    return true;
  }  

  /*
  // module configuration screen

  public function getContent()
  {
  $this->_html .= '<h2>'.$this->displayName.'</h2>';
  if (Tools::isSubmit('submit'))
  {
  $this->_postValidation();
  if (!sizeof($this->_postErrors))
  {
  $this->_postProcess();
  }
  else
  {
  foreach ($this->_postErrors AS $err)
  {
  $this->_html .= '<div class="alert error">'.$err.'</div>';
  }
  }
  }
  $this->_displayForm();
  return $this->_html;
  }
  private function _postProcess()
  {
  Configuration::updateValue($this->name.'_message', Tools::getValue('our_message'), true);
  $this->_html .= '<div class="conf confirm">'.$this->l('Settings updated').'</div>';
  }
  private function _displayForm()
  {
  $this->_html .= '
  <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
  <fieldset>
  <legend><img src="../img/admin/cog.gif" alt="" class="middle" />'.$this->l('Settings').'</legend>
  <label>'.$this->l('Message to the world').'</label>
  <div class="margin-form">
  <input type="text" name="our_message" value="'.Tools::getValue('our_message', Configuration::get($this->name.'_message')).'" />
  </div>
  <input type="submit" name="submit" value="'.$this->l('Update').'" class="button" />
  </fieldset>
  </form>';
  }

  private function _postValidation()
  {
  if (!Validate::isCleanHtml(Tools::getValue('our_message')))
  $this->_postErrors[] = $this->l('The message you entered was not allowed, sorry');
  }*/
  
  // back-office hooks

  public function hookUpdateOrderStatus($params) {

    self::sessAddMessage($this->l('The status of order changed.'), 'info');

    if ($params['newOrderStatus']->id == (int)Configuration::get('INVFOX_DELIVERED_ID'))
      return $this->generateInvoice($params);
    return false;


  }


  public function generateInvoice($params)
  {

    echo "INSIDE!!!!!!!!";

    // we get address of order if not set
    if (!isset($params['address']))
      list($params['order'], $params['address'], $params['state']) = self::getOrderInfo((int)$params['id_order']);


    //    print_r($params['address']);

    /* JM is this client address or shipping? we'll see , first we just set "client" as name
       $destination = new AvalaraAddress();
       $destination->setLine1($params['address']->address1);
       $destination->setLine2($params['address']->address2);
       $destination->setCity($params['address']->city);
       $destination->setRegion(isset($params['state']) ? $params['state']->iso_code : '');
       $destination->setPostalCode($params['address']->postcode);*/

    /*    $commitResult = $this->tax('history', array('DocCode' => (int)$params['id_order'], 'Destination' => $destination));
	  if (isset($commitResult['ResultCode']) && $commitResult['ResultCode'] == 'Success')
	  {
	  $params['CancelCode'] = 'D';
	  $this->cancelFromAvalara($params);
	  $this->cancelFromAvalara($params); // Twice because first call only voids the order, and 2nd call deletes it
	  }
    */
    $products = Db::getInstance()->ExecuteS('SELECT p.`id_product`, pl.`name`, pl.`description_short`,
						od.`product_price` as price, od.`reduction_percent`,
					        od.`reduction_amount`, od.`product_quantity` as quantity, od.tax_rate as tax_rate
						FROM `'._DB_PREFIX_.'order_detail` od
						LEFT JOIN `'._DB_PREFIX_.'product` p ON (p.id_product = od.product_id)
						LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.id_product = p.id_product)
						WHERE pl.`id_lang` = 1 AND od.`id_order` = '.(isset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order']));

    //    echo "<pre>";
    //    print_r($products);
    //echo "</pre>";*/
    
    ///$taxable = true;
    // JM -- we will need something like this also
    //check if it is outside the state and if we are in united state and if conf AVALARATAX_TAX_OUTSIDE IS ENABLE
    //if (isset($params['state']) && !Configuration::get('AVALARATAX_TAX_OUTSIDE') && $params['state']->iso_code != Configuration::get('AVALARATAX_STATE'))
    //  $taxable = false;

    //    $cart = new Cart((int)$params['order']->id_cart);
    //$getTaxResult = $this->getTax($products, array('type' => 'SalesInvoice', 'cart' => $cart, 'id_order' => isset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order'], 'taxable' => $taxable), $params['address']->id);


    /*   $commitResult = $this->tax('post', array('DocCode' => (isset($_POST['id_order']) ? (int)$_POST['id_order'] : (int)$params['id_order']),
	 'DocDate' => date('Y-m-d'),	'IdCustomer' => (int)$cart->id_customer,	'TotalAmount' => (float)$getTaxResult['TotalAmount'],
	 'TotalTax' => (float)$getTaxResult['TotalTax'])); */
					     

    /*if (isset($commitResult['ResultCode']) && ($commitResult['ResultCode'] == 'Warning' || $commitResult['ResultCode'] == 'Error' || $commitResult['ResultCode'] == 'Exception'))
      return $this->_displayConfirmation($this->l('The following error was generated while cancelling the orders you selected.'.
      '<br /> - '.Tools::safeOutput($commitResult['Messages']['Summary'])), 'error'); */


    $this->trace("INVFOX::got all data");

    $api = new InvfoxAPI(Configuration::get('INVFOX_API_KEY'), Configuration::get('INVFOX_API_DOMAIN'), true);
    $api->setDebugHook("InvFox::trace");

    $c = $params['address'];

    $r = $api->assurePartner(array(
				   'name' => $c->firstname." ".$c->lastname.($c->company?", ":"").$c->company,
				   'street' => $c->address1,
				   'postal' => $c->postcode,
				   'city' => $c->city,
				   'country' => $c->country,
				   'vatid' => $c->vat_number,
				   'phone' => $c->phone.($c->phone_mobile?", ":"").$c->phone_mobile,
				   'website' => "",
				   'email' => '', //$c->, // get email address, probalbly in some other table
				   'notes' => '',
				   'vatbound' => !!$c->vat_number,
				   'custaddr' => '',
				   'payment_period' => 5, //todo: make configurable
				   'street2' => ''
				   ));

    $this->trace("INVFOX::assured partner");
      
    if ($r->isOk()) {
	
      $this->trace("INVFOX::before create invoice");
	
      $clientIdA = $r->getData();
      $clientId = $clientIdA[0]['id'];

      $date1 = $api->_toUSDate(date('Y-m-d'));
      $invid = str_pad($params['id_order'], 5, "0", STR_PAD_LEFT); //todo: ask, check
      
      $body2 = array();
      /**/
      foreach ($products as $bl) {
	$body2[] = array(
			 'title' => $bl['name'],
			 'qty' => $bl['quantity'],
			 'mu' => '',
			 'price' => round($bl['price'] - $bl['reduction_amount'], 2),
			 'vat' => $bl['tax_rate'],
			 'discount' => $bl['reduction_percent']
			 );
      }
      /*      */
      $this->trace("INVFOX::before create invoice call");
      $r2 = $api->createInvoice(
				array(
				      'title' => $invid,
				      'date_sent' => $date1,
				      'date_to_pay' => $date1,
				      'id_partner' => $clientId
				      ),
				$body2
				);
      $this->trace("INVFOX::after create invoice");	
    }
    
    self::sessAddMessage($this->l('Invoice in InvoiceFox was created.'), 'info');

    return true;
  }
  
  
  protected function getOrderInfo($id_order)
  {
    $order = new Order((int)$id_order);
    if (!Validate::isLoadedObject($order))
      return false;

    $address = new Address((int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
    if (!Validate::isLoadedObject($address))
      return false;

    $state = null;
    if (!empty($address->id_state))
      {
	$state = new State((int)$address->id_state);
	if (!Validate::isLoadedObject($state))
	  return false;
      }

    return array($order, $address, $state);
  }
 

  function hookDisplayAdminOrder($params) {
    $this->_html .= "<br/>";
    foreach (self::sessGetMessages() AS $msg)
      {
	$this->_html .= "<div class='$msg[1]'>{$msg[0]}</div>";
      }
    self::sessClearMessages();
    return $this->_html;
  }


  // utility functions

  static function trace($x) {
    self::sessAddMessage($x);
  }

  protected function session() {	
    // Start session if needed
    if(!session_id()) {
      session_start();
    }		
    if (!isset($_SESSION['invfox_messages'])) 
      $_SESSION['invfox_messages']=array();
  }

  protected function sessAddMessage($msg, $type="info") {
    self::session();
    $_SESSION['invfox_messages'][] = array($msg, $type);
  }

  protected function sessClearMessages() {
    self::session();
    $_SESSION['invfox_messages'] = array();
  }

  protected function sessGetMessages() {
    self::session();
    return $_SESSION['invfox_messages'];
  }

  // example code
  /*
    protected function displayConfirmation($text = '', $type = 'confirm')
    {
    if ($type == 'confirm')
    $img = 'ok.gif';
    elseif ($type == 'warn')
    $img = 'warn2.png';
    elseif ($type == 'error')
    $img = 'disabled.gif';
    else
    die('Invalid type.');

    return '<div class="conf '.Tools::safeOutput($type).'">
    <img src="../img/admin/'.$img.'" alt="" title="" />
    '.(empty($text) ? $this->l('Settings updated') : $text).
    '<img src="http://www.prestashop.com/modules/avalaratax.png?sid='.urlencode(Configuration::get('AVALARATAX_ACCOUNT_NUMBER')).'" style="float: right;" />
    </div>';
    }
  */


}
?>