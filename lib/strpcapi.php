<?php

class StrpcAPI {
  
  var $apitoken = "";
  var $domain = "";
  var $debugMode = false;
  var $debugHook = "StrpcAPI::trace";
  
  static function trace($x) { echo $x; }

  function StrpcAPI($token, $domain, $debugMode=false) {
    $this->apitoken = $token;
    $this->domain = $domain;
    $this->debugMode = $debugMode;
  }
  
  function call($resource, $method, $args, $format=null, $explore=false) {
    
    $data = is_string($args) ? $args : $this->dictToParams($args, "", "&");
    
    $header = "POST /API?".($resource?"&_r={$resource}":"").($method?"&_m={$method}":"").($format?"&_f={$format}":"").($explore?"&_x=1":"")." HTTP/1.1\r\n".
      "Host:{$this->domain}\r\n".
      "Content-Type: application/x-www-form-urlencoded\r\n".
      "User-Agent: PHP-strpc-client\r\n".
      "Content-Length: " . strlen($data) . "\r\n".
      "Authorization: Basic ".base64_encode($this->apitoken.':x')."\r\n".
      "Connection: close\r\n\r\n";
    
    $result = '';
    $fp = fsockopen ("ssl://{$this->domain}", 443, $errno, $errstr, 30);
    if (!$fp) {
      call_user_func($this->debugHook, "HTTP Error");
    } else {
      if ($this->debugMode) call_user_func($this->debugHook, "<br/><div class='type'>request</div><pre class='debug'>".print_r($header . $data,true)."</pre>");
      fputs ($fp, $header . $data);
      while (!feof($fp)) {
	$result .= fgets ($fp, 128);
      }
      fclose ($fp);
    }
    if ($this->debugMode) call_user_func($this->debugHook, "<br/><div class='type'>request</div><pre class='debug'>".print_r($result,true)."</pre>");
    $resultD = str_replace("'", '"', trim(substr($result, strpos($result, "\r\n\r\n") + 4)));
    return new StrpcRes(json_decode($resultD, true));
    
  }
  
  function dictToParams($array, $startWith='?', $delim='&amp;')
  {
    $r = array();
    foreach ($array as $key => $val)
      {
	$r[] = "$key=$val";
      }
    return $startWith . implode($delim, $r);
  }
  
  
}

class StrpcRes {
  
  var $res;
  
  function StrpcRes($res) {
    $this->res = $res;
  }
  
  function isErr() { return false; } /// TODO -- look at HTTP response codes for errors (like validation, ect..) and communicate this out !!!!!!!!!!!!!!!!!!!!!!!!!!
  
  function isOk() { return true; }
  
  function getErr() { return print_r($this->res, true); }
  
  function getData() { return $this->res[0]; }
  
}
?>