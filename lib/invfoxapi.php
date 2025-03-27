<?php
/**
 * InvfoxAPI - High-level API client for Cebelca BIZ service
 * 
 * This class provides methods for interacting with the Cebelca BIZ API
 */

// Global response headers array for PDF downloads
$responseHeaders = array();

/**
 * Generate a random string for unique filenames
 * 
 * @param int $length Length of the random string
 * @return string Random string
 */
function generateRandomString($length = 12) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $randomString;
}

/**
 * Read header callback for cURL
 * 
 * @param resource $ch cURL handle
 * @param string $header Header line
 * @return int Length of the header
 */
function readHeader($ch, $header) {
    if (defined('WOOCOMM_INVFOX_DEBUG') && WOOCOMM_INVFOX_DEBUG) {
        woocomm_invfox__trace($header, "READ HEADERS");
    }
    
    global $responseHeaders;
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $responseHeaders[$url][] = $header;
    
    return strlen($header);
}

/**
 * InvfoxAPI class for interacting with Cebelca BIZ API
 */
class InvfoxAPI {
  /**
   * StrpcAPI instance
   * @var StrpcAPI
   */
  protected $api;
  
  /**
   * Constructor
   * 
   * @param string $apitoken API token
   * @param string $apidomain API domain
   * @param bool $debugMode Debug mode flag
   */
  public function __construct($apitoken, $apidomain, $debugMode=false) {
    if (empty($apitoken)) {
      throw new Exception("API token is required");
    }
    
    if (empty($apidomain)) {
      $apidomain = "www.cebelca.biz";
    }
    
    $this->api = new StrpcAPI($apitoken, $apidomain, $debugMode);
  }

  /**
   * Set debug hook function
   * 
   * @param string $hook Debug hook function name
   */
  public function setDebugHook($hook) {
    $this->api->debugHook = $hook;
  }

  /**
   * Ensure partner exists, create if not
   * 
   * @param array $data Partner data
   * @return StrpcRes API response
   * @throws Exception If API call fails
   */
  public function assurePartner($data) {
    $res = $this->api->call('partner', 'assure', $data);
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to assure partner: " . print_r($res->getErr(), true), "ERROR");
    }
    return $res;
  }

  /**
   * Create an invoice
   * 
   * @param array $header Invoice header data
   * @param array $body Invoice body data (line items)
   * @return StrpcRes API response
   * @throws Exception If API call fails
   */
  public function createInvoice($header, $body) {
    $res = $this->api->call('invoice-sent', 'insert-smart-2', $header);
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to create invoice: " . print_r($res->getErr(), true), "ERROR");
    } else {
      $resD = $res->getData();
      if (!empty($resD[0]['id'])) {
        $invoiceId = $resD[0]['id'];
        
        foreach ($body as $bl) {
          $bl['id_invoice_sent'] = $invoiceId;
          $res2 = $this->api->call('invoice-sent-b', 'insert-into', $bl);
          if ($res2->isErr()) {
            woocomm_invfox__trace("Failed to add line item to invoice: " . print_r($res2->getErr(), true), "ERROR");
          }
        }
      } else {
        woocomm_invfox__trace("Invalid response from API: missing invoice ID", "ERROR");
      }
    }
    return $res;
  }

  /**
   * Create a proforma invoice
   * 
   * @param array $header Proforma invoice header data
   * @param array $body Proforma invoice body data (line items)
   * @return StrpcRes API response
   * @throws Exception If API call fails
   */
  public function createProFormaInvoice($header, $body) {
    $res = $this->api->call('preinvoice', 'insert-smart', $header);
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to create proforma invoice: " . print_r($res->getErr(), true), "ERROR");
    } else {
      $resD = $res->getData();
      if (!empty($resD[0]['id'])) {
        $invoiceId = $resD[0]['id'];
        
        foreach ($body as $bl) {
          $bl['id_preinvoice'] = $invoiceId;
          $res2 = $this->api->call('preinvoice-b', 'insert-into', $bl);
          if ($res2->isErr()) {
            woocomm_invfox__trace("Failed to add line item to proforma invoice: " . print_r($res2->getErr(), true), "ERROR");
          }
        }
      } else {
        woocomm_invfox__trace("Invalid response from API: missing proforma invoice ID", "ERROR");
      }
    }
    return $res;
  }

  /**
   * Create an inventory sale
   * 
   * @param array $header Inventory sale header data
   * @param array $body Inventory sale body data (line items)
   * @return StrpcRes API response
   * @throws Exception If API call fails
   */
  public function createInventorySale($header, $body) {
    $res = $this->api->call('transfer', 'insert-smart', $header);
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to create inventory sale: " . print_r($res->getErr(), true), "ERROR");
    } else {
      $resD = $res->getData();
      if (!empty($resD[0]['id'])) {
        $saleId = $resD[0]['id'];
        
        foreach ($body as $bl) {
          $bl['id_transfer'] = $saleId;
          $res2 = $this->api->call('transfer-b', 'insert-into', $bl);
          if ($res2->isErr()) {
            woocomm_invfox__trace("Failed to add line item to inventory sale: " . print_r($res2->getErr(), true), "ERROR");
          }
        }
      } else {
        woocomm_invfox__trace("Invalid response from API: missing inventory sale ID", "ERROR");
      }
    }
    return $res;
  }

  /**
   * Download a PDF document
   * 
   * @param int $id Document ID
   * @param int $extid External document ID
   * @param string $path Path to save the PDF
   * @param string $res Resource type (invoice-sent, preinvoice, transfer)
   * @param string $hstyle Header style
   * @return string Path to the downloaded PDF
   */
  public function downloadPDF($id, $extid, $path, $res='invoice-sent', $hstyle='') {
    // Set document title based on resource type
    $title = "Račun%20št.";
    if ($res == "preinvoice") {
      $title = "Predračun%20št.";
    } else if ($res == "transfer") {
      $title = "Dobavnica%20št.";
    }
    
    // Determine prefix for filename
    $prefix = "racun";
    switch($res) {
      case "invoice-sent":
        $prefix = "racun";
        break;
      case "preinvoice":
        $prefix = "predracun";
        break;
      case "transfer":
        $prefix = "dobavnica";
        break;
    }
    
    // Try to use cURL if available
    if (function_exists('curl_init')) {
      $url = "https://{$this->api->getDomain()}/API-pdf?id=$id&extid=$extid&res={$res}&format=PDF&doctitle={$title}&lang=si&hstyle={$hstyle}";
      
      woocomm_invfox__trace("Downloading PDF from: $url", "DOWNLOADING PDF");
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Basic " . base64_encode($this->api->getApiToken() . ':x')
      ));
      
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
      if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        woocomm_invfox__trace("Error downloading PDF: $error", "ERROR");
        return false;
      }
      
      curl_close($ch);
      
      if ($httpCode >= 400) {
        woocomm_invfox__trace("Error downloading PDF: HTTP code $httpCode", "ERROR");
        return false;
      }
      
      // Generate filename and save file
      $rand = generateRandomString(12);
      $file = $path . "/{$prefix}_{$id}_{$extid}_{$rand}.pdf";
      
      if (file_put_contents($file, $response) === false) {
        woocomm_invfox__trace("Failed to save PDF to: $file", "ERROR");
        return false;
      }
      
      return $file;
    } else {
      // Fallback to file_get_contents
      $opts = array(
        'http' => array(
          'method' => "GET",
          'header' => "Authorization: Basic " . base64_encode($this->api->getApiToken() . ':x') . "\r\n"
        )
      );
      
      $context = stream_context_create($opts);
      $url = "https://{$this->api->getDomain()}/API-pdf?id=$id&extid=$extid&res={$res}&format=PDF&doctitle={$title}&lang=si&hstyle={$hstyle}";
      
      woocomm_invfox__trace("Downloading PDF from: $url", "DOWNLOADING PDF");
      
      $data = @file_get_contents($url, false, $context);
      
      if ($data === false) {
        woocomm_invfox__trace("Error downloading PDF", "ERROR");
        return false;
      }
      
      // Generate filename and save file
      $rand = generateRandomString(12);
      $file = $path . "/{$prefix}_{$id}_{$extid}_{$rand}.pdf";
      
      if (file_put_contents($file, $data) === false) {
        woocomm_invfox__trace("Failed to save PDF to: $file", "ERROR");
        return false;
      }
      
      return $file;
    }
  }

  /**
   * Mark an invoice as paid
   * 
   * @param int $id Invoice ID
   * @param string $payment_method Payment method
   * @return StrpcRes API response
   */
  public function markInvoicePaid($id, $payment_method) {
    $res = $this->api->call('invoice-sent-p', 'mark-paid-2', array(
      'id_invoice_sent_ext' => $id, 
      'date_of' => date("Y-m-d"), 
      'amount' => 0, 
      'payment_method' => $payment_method, 
      'id_invoice_sent' => 0
    ));
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to mark invoice as paid: " . print_r($res->getErr(), true), "ERROR");
    }
    
    return $res;
  }
  
  /**
   * Mark an invoice as paid by invoice ID
   * 
   * @param int $invid Invoice ID
   * @param string $payment_method Payment method
   * @return StrpcRes API response
   */
  public function markInvoicePaid2($invid, $payment_method) {
    $res = $this->api->call('invoice-sent-p', 'mark-paid-2', array(
      'id_invoice_sent_ext' => 0, 
      'date_of' => date("Y-m-d"), 
      'amount' => 0, 
      'payment_method' => $payment_method, 
      'id_invoice_sent' => $invid
    ));
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to mark invoice as paid: " . print_r($res->getErr(), true), "ERROR");
    }
    
    return $res;
  }
  
  /**
   * Create an inventory document from an invoice
   * 
   * @param int $invid Invoice ID
   * @param int $from From warehouse ID
   * @param int $to To warehouse ID
   * @return StrpcRes API response
   */
  public function makeInventoryDocOutOfInvoice($invid, $from, $to) {
    $res = $this->api->call('transfer', 'make-inventory-doc-smart', array(
      'id_invoice_sent' => $invid, 
      'date_created' => $this->_toSIDate(date("Y-m-d")), 
      'docsubtype' => 0, 
      'doctype' => 1,
      'negate_qtys' => 0,
      'id_contact_from' => $from,
      'id_contact_to' => $to,
      'combine_parts' => 0
    ));
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to create inventory document: " . print_r($res->getErr(), true), "ERROR");
    }
    
    return $res;
  }
  
  /**
   * Check inventory items
   * 
   * @param array $items Items to check
   * @param int $warehouse Warehouse ID
   * @param string $date Date
   * @return array Inventory check results
   */
  public function checkInvtItems($items, $warehouse, $date) {
    $skv = "";
    foreach ($items as $item) {
      if (!empty($item['code']) && isset($item['qty'])) {
        $skv .= $item['code'] . ";" . $item['qty'] . "|";
      }
    }
    
    if (empty($skv)) {
      woocomm_invfox__trace("No valid items to check", "ERROR");
      return array();
    }
    
    $res = $this->api->call('item', 'check-items', array(
      "just-for-items" => $skv, 
      "warehouse" => $warehouse, 
      "date" => $date
    ));
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to check inventory items: " . print_r($res->getErr(), true), "ERROR");
      return array();
    } 
    
    return $res->getData();
  }

  /**
   * Convert date to US format
   * 
   * @param string $d Date string
   * @return string Date in US format
   */
  public function _toUSDate($d) {
    if (strpos($d, "-") > 0) {
      $da = explode(" ", $d);
      $d1 = explode("-", $da[0]);
      return $d1[1] . "/" . $d1[2] . "/" . $d1[0];
    } else {
      return $d;
    }
  }

  /**
   * Convert date to SI format
   * 
   * @param string $d Date string
   * @return string Date in SI format
   */
  public function _toSIDate($d) {
    if (strpos($d, "-") > 0) {
      $da = explode(" ", $d);
      $d1 = explode("-", $da[0]);
      return $d1[2] . "." . $d1[1] . "." . $d1[0];
    } else {
      return $d;
    }
  }

  /**
   * Finalize an invoice with fiscal data
   * 
   * @param array $header Invoice header data
   * @return array|bool Finalized invoice data or false on error
   */
  public function finalizeInvoice($header) {
    if (empty($header['id'])) {
      woocomm_invfox__trace("Invoice ID is required for finalization", "ERROR");
      return false;
    }
    
    $res = $this->api->call('invoice-sent', 'finalize-invoice', $header);
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to finalize invoice: " . print_r($res->getErr(), true), "ERROR");
      return false;
    } else {
      $resD = $res->getData();
      return $resD;
    }
  }
  
  /**
   * Finalize an invoice without fiscal data
   * 
   * @param array $header Invoice header data
   * @return array|bool Finalized invoice data or false on error
   */
  public function finalizeInvoiceNonFiscal($header) {
    if (empty($header['id'])) {
      woocomm_invfox__trace("Invoice ID is required for finalization", "ERROR");
      return false;
    }
    
    $res = $this->api->call('invoice-sent', 'finalize-invoice-2015', $header);
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to finalize non-fiscal invoice: " . print_r($res->getErr(), true), "ERROR");
      return false;
    } else {
      $resD = $res->getData();
      return $resD;
    }
  }

  /**
   * Get fiscal information for an invoice
   * 
   * @param int $id Invoice ID
   * @return array|bool Fiscal information or false on error
   */
  public function getFiscalInfo($id) {
    if (empty($id)) {
      woocomm_invfox__trace("Invoice ID is required to get fiscal info", "ERROR");
      return false;
    }
    
    $res = $this->api->call('invoice-sent', 'get-fiscal-info', array('id' => $id));
    
    if ($res->isErr()) {
      woocomm_invfox__trace("Failed to get fiscal info: " . print_r($res->getErr(), true), "ERROR");
      return false;
    } else {
      $resD = $res->getData();
      return $resD;
    }
  }
}
?>
