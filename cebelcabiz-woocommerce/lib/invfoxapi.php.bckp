<?php

class InvfoxAPI {

  var $api;

  function InvfoxAPI($apitoken, $apidomain, $debugMode=false) {
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
    //		print_r($res);
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
    //		print_r($res);
    if ($res->isErr()) {
      echo 'error' . $res->getErr();
    } else {
      foreach ($body as $bl) {
	$resD = $res->getData();
	print_r($resD);
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
    //		print_r($res);
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

  function downloadPDF($id, $path, $res='invoice-sent', $hstyle='') {
    // $res - invoice-sent / preinvoice / transfer
    echo $id;
    $opts = array(
		  'http'=>array(
				'method'=>"GET",
				'header'=>"Authorization: Basic ".base64_encode($this->api->apitoken.':x')."\r\n" 
				)
		  );
    echo "https://{$this->api->domain}/API-pdf?id=$id&res=invoice-sent&format=pdf&doctitle=Invoice%20No.&lang=en&res=invoice-sent";
    $context = stream_context_create($opts);
    $data = file_get_contents("https://{$this->api->domain}/API-pdf?id=$id&res={$res}&format=PDF&doctitle=Invoice%20No.&lang=si&hstyle={$hstyle}", false, $context);
		
    if ($data === false) {
      echo 'error downloading PDF';
    } else {
      $file = $path."/".$id.".pdf";
      file_put_contents($file, $data);
    }
  }

  function markInvoicePaid($id, $payment_method=0) {
    $res = $this->api->call('invoice-sent-p', 'mark-paid', array('id_invoice_sent_ext' => $id, 
							       'date_of' => date("Y-m-d"), 'amount' => 0, 'id_payment_method' => $payment_method, 'id_invoice_sent' => 0));
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



}

?>
