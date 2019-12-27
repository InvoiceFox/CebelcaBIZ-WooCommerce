<?php
class InvfoxAPI {

  var $api;

  function __construct($apitoken, $apidomain, $debugMode=false) {
    $this->api = new StrpcAPI($apitoken, $apidomain, $debugMode);
  }

  function setDebugHook($hook) {
    $this->api->debugHook = $hook;
  }

  function assurePartner($data) {
    $res = $this->api->call('partner', 'assure', $data);
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    }
    return $res;
  }

  function createInvoice($header, $body) {
    $res = $this->api->call('invoice-sent', 'insert-smart-2', $header);
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    } else {
      foreach ($body as $bl) {
  $resD = $res->getData();
  //print_r($resD);
  $bl['id_invoice_sent'] = $resD[0]['id'];
  $res2 = $this->api->call('invoice-sent-b', 'insert-into', $bl);
  if ($res2->isErr()) {
    echo 'error' . $res->getErr();
  } 
      }
    }
    return $res;
  }

  function createProFormaInvoice($header, $body) {
    $res = $this->api->call('preinvoice', 'insert-smart', $header);
    //    print_r($res);
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    } else {
      foreach ($body as $bl) {
  $resD = $res->getData();
  //print_r($resD);
  $bl['id_preinvoice'] = $resD[0]['id'];
  $res2 = $this->api->call('preinvoice-b', 'insert-into', $bl);
  if ($res2->isErr()) {
    echo 'error' . $res->getErr();
  } 
      }
    }
    return $res;
  }

  function createInventorySale($header, $body) {
    $res = $this->api->call('transfer', 'insert-smart', $header);
    //    print_r($res);
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    } else {
      foreach ($body as $bl) {
  $resD = $res->getData();
  //print_r($resD);
  $bl['id_transfer'] = $resD[0]['id'];
  $res2 = $this->api->call('transfer-b', 'insert-into', $bl);
  if ($res2->isErr()) {
    echo 'error' . $res->getErr();
  } 
      }
    }
    return $res;
  }

  // id - of the invoice
  // extid - external id of the invoice, for example orderid if you used id_invoice_sent_ext to create it (you set id to 0 if this is used to define invoice)
  // path - path where invoices are stored 
  // res - resource of the PDF (invoice-sent / preinvoice / transfer)
  // hstyle - runtime header_style if needed (othervise taken from user settings)
  function downloadPDF($id, $extid, $path, $res='invoice-sent', $hstyle='') {
    // $res - invoice-sent / preinvoice / transfer
    echo $id;
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"Authorization: Basic ".base64_encode($this->api->apitoken.':x')."\r\n" 
        )
      );
    $context = stream_context_create($opts);
    $data = file_get_contents("https://{$this->api->domain}/API-pdf?id=$id&extid=$extid&res={$res}&format=PDF&doctitle=Račun%20št.&lang=si&hstyle={$hstyle}", false, $context);

    if ($data === false) {
      echo 'error downloading PDF';
    } else {
      $file = $path."/racun_{$id}_{$extid}.pdf";
      file_put_contents($file, $data);
      return $file;
    }
  }

  // TODO -- this can be removed I think and replaced with above with args
  function downloadInvoicePDF($id, $path, $res='invoice-sent', $hstyle='') {
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"Authorization: Basic ".base64_encode($this->api->apitoken.':x')."\r\n" 
        )
      );
    $context = stream_context_create($opts);
    $data = file_get_contents("http://{$this->api->domain}/API-pdf?id=0&extid={$id}&res={$res}&format=PDF&doctitle=Invoice%20No.&lang=si&hstyle=$hstyle", false, $context);

    if ($data === false) {
      echo 'error downloading PDF';
    } else {
      $file = $path . "/invoice_eat2live_" . $id . ".pdf";
      file_put_contents($file, $data);
      return $file;
    }
  }

  function markInvoicePaid($id, $payment_method=1) {
    $res = $this->api->call('invoice-sent-p', 'mark-paid', array('id_invoice_sent_ext' => $id, 
                                                                 'date_of' => date("Y-m-d"), 'amount' => 0, 'id_payment_method' => $payment_method, 'id_invoice_sent' => 0));
    
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    }
  }
  
  function markInvoicePaid2($invid, $payment_method=1) {
    $res = $this->api->call('invoice-sent-p', 'mark-paid', array('id_invoice_sent_ext' => 0, 
                                                                 'date_of' => date("Y-m-d"), 'amount' => 0, 'id_payment_method' => $payment_method, 'id_invoice_sent' => $invid));
    
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    }
  }
  

  function makeInventoryDocOutOfInvoice($invid, $from, $to) {
    $res = $this->api->call('transfer', 'make-inventory-doc-smart', array('id_invoice_sent' => $invid, 
                                                                          'date_created' => $this->_toSIDate( date("Y-m-d") ), 
                                                                          'docsubtype' => 0, 'doctype' => 1,
                                                                          'negate_qtys' => 0,
                                                                          'id_contact_from' => $from,
                                                                          'id_contact_to' => $to));
    
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    }
  }
  
  function checkInvtItems($items, $warehouse, $date) {
    $skv = "";
    foreach ($items as $item) {
      $skv .= $item['code'].";".$item['qty']."|";
    }
    $res2 = $this->api->call('item', 'check-items', array("just-for-items" => $skv, "warehouse" => $warehouse, "date" => $date));
    if ($res2->isErr()) {
      echo 'error' . $res->getErr();
    } 
    return $res2->getData();
    // TODO -- return what is not on inventory OR item missing OR if all is OK
  }


  function _toUSDate($d) {
    if (strpos($d, "-") > 0) {
      $da = explode(" ", $d);
      $d1 = explode("-", $da[0]);
      return $d1[1]."/".$d1[2]."/".$d1[0];
    } else {
      return $d;
    }
  }

  function _toSIDate($d) {
    if (strpos($d, "-") > 0) {
      $da = explode(" ", $d);
      $d1 = explode("-", $da[0]);
      return $d1[2].".".$d1[1].".".$d1[0];
    } else {
      return $d;
    }
  }

  // TODO - should probably remove and use only downloadPDF
  function printInvoice($id, $res='invoice-sent',$hstyle='basicVER3') { //basicVER3UPN
    // $res - invoice-sent / preinvoice / transfer
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"Authorization: Basic ".base64_encode($this->api->apitoken.':x')."\r\n" 
        )
      );
    $context = stream_context_create($opts); //Predračun%20št. Račun%20št. //inv-template basic modern elegant basicVER3  basicVER3UPN modernVER3 elegantVER3
  $data = file_get_contents("http://{$this->api->domain}/API-pdf?id=0&extid={$id}&res={$res}&format=PDF&doctitle=".urlencode('Invoice No.')."&lang=si&hstyle=$hstyle", false, $context);
    if ($data === false) { 
      echo 'error downloading PDF';
    } else {
      
    header('Content-type: application/pdf');
    header('Content-Disposition: inline; filename="invoice.pdf"');
      header('Content-Transfer-Encoding: binary');
      header('Accept-Ranges: bytes');
    echo $data;
    }
  }

  // header should be array with keys  
  // id - id of the invoice
  // id_location - fiscal invoice needs predefined location (more about that below). Location must also be sent to Tax Office - registered with them.
  // fiscalize - you can have optional fiscalisation where you only fiscalize "cash" invoices. If you aren't in fiscal system at all you don't need to define location and you don't use this call at all. Also invoice numbering is different in that case. More about it later. Can be 1 or 0.
  // op-tax-id - operators tax id. Personal tax ID of the person issuing an invoice (gets sent to Tax office)
  // op-name - operators handle/nickname (can be name), is printed on invoice
  // test_mode - fiscalizes to TEST Tax Office (FURS) server. Before you do this you must register your location at TEST FURS server too. More about it below. Can be 1 or 0.
  function finalizeInvoice($header)
  {
  $res = $this->api->call('invoice-sent', 'finalize-invoice', $header);
  if ($res->isErr()) {
    echo 'error' . $res->getErr();
  } else {
    $resD = $res->getData();
    return $resD;
  }
  }
  
  // header should be array with keys
  // id (invoice ID)
  // title (empty for program to automatically fill with the next one)
  // doctype (0 for invoices)
  // returns for example: [{"new_title":"18-0005"}]
  function finalizeInvoiceNonFiscal($header)
  {
  $res = $this->api->call('invoice-sent', 'finalize-invoice-2015', $header);
  if ($res->isErr()) {
    echo 'error' . $res->getErr();
  } else {
    $resD = $res->getData();
    return $resD;
  }
  }

  function getFiscalInfo($id) {
  $res = $this->api->call('invoice-sent', 'get-fiscal-info', array('id' => $id));
  if ($res->isErr()) {
    echo 'error' . $res->getErr();
    return false;
  } else {
    $resD = $res->getData();
    return $resD;
  }
  }



}
?>
