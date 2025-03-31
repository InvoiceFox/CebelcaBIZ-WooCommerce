<?php
/**
 * StrpcAPI - API client for Cebelca BIZ service
 * 
 * This class handles the low-level communication with the Cebelca BIZ API
 */

class StrpcAPI {
  /**
   * Get the API domain
   * 
   * @return string API domain
   */
  public function getDomain() {
    return $this->domain;
  }
  
  /**
   * Get the API token
   * 
   * @return string API token
   */
  public function getApiToken() {
    return $this->apitoken;
  }
  
  /**
   * API token for authentication
   * @var string
   */
  protected $apitoken = "";
  
  /**
   * API domain
   * @var string
   */
  protected $domain = "";
  
  /**
   * Debug mode flag
   * @var bool
   */
  protected $debugMode = false;
  
  /**
   * Debug hook function name
   * @var string
   */
  public $debugHook = "StrpcAPI::trace";
  
  /**
   * Maximum number of retries for API calls
   * @var int
   */
  protected $maxRetries = 2;
  
  /**
   * Default timeout for API calls in seconds
   * @var int
   */
  protected $timeout = 30;
  
  /**
   * Default trace function
   * 
   * @param mixed $x Data to trace
   * @param string $context Optional context label
   */
  public static function trace($x, $context = "StrpcAPI") { 
    if (defined('WOOCOMM_INVFOX_DEBUG') && WOOCOMM_INVFOX_DEBUG) {
      $formatted_x = is_string($x) ? $x : print_r($x, true);
      error_log("[{$context}] {$formatted_x}"); 
    }
  }

  /**
   * Constructor
   * 
   * @param string $token API token
   * @param string $domain API domain
   * @param bool $debugMode Debug mode flag
   */
  public function __construct($token, $domain, $debugMode=false) {
    $this->apitoken = $token;
    $this->domain = $domain;
    $this->debugMode = $debugMode;
  }
  
  /**
   * Make an API call
   * 
   * @param string $resource API resource
   * @param string $method API method
   * @param array|string $args API arguments
   * @param string|null $format Response format
   * @param bool $explore Explore mode
   * @return StrpcRes API response
   * @throws Exception If API call fails after retries
   */
  public function call($resource, $method, $args, $format=null, $explore=false) {
    // Try to use cURL if available for better error handling
    if (function_exists('curl_init')) {
      return $this->callWithCurl($resource, $method, $args, $format, $explore);
    } else {
      return $this->callWithFsockopen($resource, $method, $args, $format, $explore);
    }
  }
  
  /**
   * Make an API call using cURL
   * 
   * @param string $resource API resource
   * @param string $method API method
   * @param array|string $args API arguments
   * @param string|null $format Response format
   * @param bool $explore Explore mode
   * @return StrpcRes API response
   * @throws Exception If API call fails after retries
   */
  protected function callWithCurl($resource, $method, $args, $format=null, $explore=false) {
    $data = is_string($args) ? $args : $this->dictToParams($args, "", "&");
    $url = "https://{$this->domain}/API?".
           ($resource ? "_r={$resource}&" : "").
           ($method ? "_m={$method}&" : "").
           ($format ? "_f={$format}&" : "").
           ($explore ? "_x=1&" : "");
    
    // Remove trailing & if present
    $url = rtrim($url, '&');
    
    $retries = 0;
    $lastError = '';
    
    while ($retries <= $this->maxRetries) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: PHP-strpc-client',
        'Authorization: Basic ' . base64_encode($this->apitoken . ':x')
      ));
      
      if ($this->debugMode) {
        $debugData = "URL: $url\nHeaders: " . print_r(array(
          'Content-Type: application/x-www-form-urlencoded',
          'User-Agent: PHP-strpc-client',
          'Authorization: Basic ' . base64_encode($this->apitoken . ':x')
        ), true) . "\nData: $data";
        call_user_func($this->debugHook, $debugData, "API Request");
      }
      
      $result = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
      if ($result === false) {
        $lastError = curl_error($ch);
        curl_close($ch);
        $retries++;
        
        if ($this->debugMode) {
          call_user_func($this->debugHook, "API call failed (attempt $retries): $lastError", "API Error");
        }
        
        if ($retries <= $this->maxRetries) {
          // Wait before retrying (exponential backoff)
          $waitTime = pow(2, $retries - 1);
          if ($this->debugMode) {
            call_user_func($this->debugHook, "Waiting {$waitTime}s before retry", "API Retry");
          }
          sleep($waitTime);
          continue;
        }
        
        throw new Exception("API call failed after {$this->maxRetries} retries: $lastError");
      }
      
      curl_close($ch);
      
      if ($this->debugMode) {
        // Truncate very long responses for readability
        $resultForLog = $result;
        if (strlen($resultForLog) > 1000) {
          $resultForLog = substr($resultForLog, 0, 1000) . "... [truncated, total length: " . strlen($result) . "]";
        }
        call_user_func($this->debugHook, "API response (HTTP $httpCode): " . print_r($resultForLog, true), "API Response");
      }
      
      // Check for HTTP errors
      if ($httpCode >= 400) {
        if ($retries < $this->maxRetries) {
          $retries++;
          $waitTime = pow(2, $retries - 1);
          if ($this->debugMode) {
            call_user_func($this->debugHook, "HTTP error $httpCode, waiting {$waitTime}s before retry", "API HTTP Error");
          }
          sleep($waitTime);
          continue;
        }
        
        throw new Exception("API returned error HTTP code: $httpCode");
      }
      
      // Process the response
      $resultData = json_decode($result, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to clean up the response if it's not valid JSON
        $result = str_replace("'", '"', trim($result));
        $resultData = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
          if ($retries < $this->maxRetries) {
            $retries++;
            $waitTime = pow(2, $retries - 1);
            if ($this->debugMode) {
              call_user_func($this->debugHook, "JSON parse error: " . json_last_error_msg() . ", waiting {$waitTime}s before retry", "API JSON Error");
            }
            sleep($waitTime);
            continue;
          }
          
          throw new Exception("Failed to parse API response as JSON: " . json_last_error_msg());
        }
      }
      
      return new StrpcRes($resultData);
    }
    
    // This should never be reached due to the exception in the loop
    throw new Exception("API call failed after {$this->maxRetries} retries");
  }
  
  /**
   * Make an API call using fsockopen (fallback method)
   * 
   * @param string $resource API resource
   * @param string $method API method
   * @param array|string $args API arguments
   * @param string|null $format Response format
   * @param bool $explore Explore mode
   * @return StrpcRes API response
   * @throws Exception If API call fails
   */
  protected function callWithFsockopen($resource, $method, $args, $format=null, $explore=false) {
    $data = is_string($args) ? $args : $this->dictToParams($args, "", "&");
    
    $header = "POST /API?".($resource?"&_r={$resource}":"").($method?"&_m={$method}":"").($format?"&_f={$format}":"").($explore?"&_x=1":"")." HTTP/1.1\r\n".
      "Host:{$this->domain}\r\n".
      "Content-Type: application/x-www-form-urlencoded\r\n".
      "User-Agent: PHP-strpc-client\r\n".
      "Content-Length: " . strlen($data) . "\r\n".
      "Authorization: Basic ".base64_encode($this->apitoken.':x')."\r\n".
      "Connection: close\r\n\r\n";
    
    $result = '';
    $retries = 0;
    $lastError = '';
    
    while ($retries <= $this->maxRetries) {
      $errno = 0;
      $errstr = '';
      
      $fp = @fsockopen("ssl://{$this->domain}", 443, $errno, $errstr, $this->timeout);
      
      if (!$fp) {
        $lastError = "HTTP Error ($errno): $errstr";
        $retries++;
        
        if ($this->debugMode) {
          call_user_func($this->debugHook, "API connection failed (attempt $retries): $lastError");
        }
        
        if ($retries <= $this->maxRetries) {
          // Wait before retrying (exponential backoff)
          sleep(pow(2, $retries - 1));
          continue;
        }
        
        throw new Exception("API connection failed after {$this->maxRetries} retries: $lastError");
      }
      
      if ($this->debugMode) {
        call_user_func($this->debugHook, "API request: " . print_r($header . $data, true), "API Request (fsockopen)");
      }
      
      // Set socket timeout
      stream_set_timeout($fp, $this->timeout);
      
      // Send request
      fwrite($fp, $header . $data);
      
      // Read response
      $result = '';
      while (!feof($fp)) {
        $result .= fgets($fp, 4096);
        
        // Check for timeout
        $info = stream_get_meta_data($fp);
        if ($info['timed_out']) {
          fclose($fp);
          
          if ($retries < $this->maxRetries) {
            $retries++;
            sleep(pow(2, $retries - 1));
            continue 2; // Continue the outer loop
          }
          
          throw new Exception("API request timed out after {$this->maxRetries} retries");
        }
      }
      
      fclose($fp);
      
      if ($this->debugMode) {
        // Truncate very long responses for readability
        $resultForLog = $result;
        if (strlen($resultForLog) > 1000) {
          $resultForLog = substr($resultForLog, 0, 1000) . "... [truncated, total length: " . strlen($result) . "]";
        }
        call_user_func($this->debugHook, "API response: " . print_r($resultForLog, true), "API Response (fsockopen)");
      }
      
      // Extract the response body
      $headerEnd = strpos($result, "\r\n\r\n");
      if ($headerEnd === false) {
        if ($retries < $this->maxRetries) {
          $retries++;
          sleep(pow(2, $retries - 1));
          continue;
        }
        
        throw new Exception("Invalid API response format (no header separator)");
      }
      
      $body = trim(substr($result, $headerEnd + 4));
      
      // Check for HTTP status code in the response
      if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $result, $matches)) {
        $statusCode = (int)$matches[1];
        
        if ($statusCode >= 400) {
          if ($retries < $this->maxRetries) {
            $retries++;
            sleep(pow(2, $retries - 1));
            continue;
          }
          
          throw new Exception("API returned error HTTP code: $statusCode");
        }
      }
      
      // Process the response
      $body = str_replace("'", '"', $body);
      $resultData = json_decode($body, true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        if ($retries < $this->maxRetries) {
          $retries++;
          sleep(pow(2, $retries - 1));
          continue;
        }
        
        throw new Exception("Failed to parse API response as JSON: " . json_last_error_msg());
      }
      
      return new StrpcRes($resultData);
    }
    
    // This should never be reached due to the exception in the loop
    throw new Exception("API call failed after {$this->maxRetries} retries");
  }
  
  /**
   * Convert an associative array to URL parameters
   * 
   * @param array $array Input array
   * @param string $startWith Starting character
   * @param string $delim Delimiter
   * @return string URL parameters
   */
  protected function dictToParams($array, $startWith='?', $delim='&amp;') {
    if (!is_array($array)) {
      return '';
    }
    
    $r = array();
    foreach ($array as $key => $val) {
      if ($val === null) {
        continue; // Skip null values
      }
      $r[] = urlencode($key) . "=" . urlencode($val);
    }
    
    return empty($r) ? '' : $startWith . implode($delim, $r);
  }
}

/**
 * StrpcRes - API response handler
 */
class StrpcRes {
  /**
   * Raw API response
   * @var array
   */
  protected $res;
  
  /**
   * Constructor
   * 
   * @param array $res API response
   */
  public function __construct($res) {
    $this->res = $res;
  }
  
  /**
   * Check if the response contains an error
   * 
   * @return bool True if the response contains an error
   */
  public function isErr() {
    if (!is_array($this->res) || empty($this->res)) {
      return true;
    }
    
    if ($this->res[0] === "validation") {
      return true;
    }
    
    if (isset($this->res[0][0]) && is_array($this->res[0][0]) && array_key_exists('err', $this->res[0][0])) {
      return true;
    }
    
    return false;
  }
  
  /**
   * Check if the response is OK
   * 
   * @return bool True if the response is OK
   */
  public function isOk() {
    return !$this->isErr();
  }
  
  /**
   * Get the error message from the response
   * 
   * @return string|array Error message
   */
  public function getErr() {
    if (!is_array($this->res) || empty($this->res)) {
      return "Empty response";
    }
    
    if ($this->res[0] === "validation") {
      return isset($this->res[1]) ? $this->res[1] : "Validation error";
    }
    
    if (isset($this->res[0][0]) && is_array($this->res[0][0]) && array_key_exists('err', $this->res[0][0])) {
      return $this->res[0][0]['err'];
    }
    
    return "Unknown error";
  }
  
  /**
   * Get the data from the response
   * 
   * @return array Response data
   */
  public function getData() {
    return isset($this->res[0]) ? $this->res[0] : array();
  }
}
?>
