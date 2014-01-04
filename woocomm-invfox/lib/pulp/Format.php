<?php
/*	farmat class
	for various text formatings and works
 */

require_once 'pulp/General.php';

  
class Format {
/** Class with various little functions for text formating and validating. **/

 	function tags_nls( $text ) {
 	/** Disables tags and inserts br-s. **/
		$text = str_replace( array( '<', '>'), array('&lt', '&gt'), $text);
		return $this->br( $text );
	}
	
 	function br( $text ) {
 	/** Changes \n with <br />. **/
		return str_replace( "\n", '<br />', $text);
	}

 	function tabs( $text ) {
 	/** Changes \n with <br />. **/
		return str_replace( "\t", '&#160;&#160;&#160;&#160;', $text);
	}

	function nbsp( $text ) {
 	/** Changes \n with <br />. **/
		return str_replace( " ", '&#160;', $text);
	}

 	function br_plus( $text ) {
 	/** Changes \n with <br />. **/
 		$text =  str_replace( "\r", "", $text);
		return str_replace( "\n", '<br />', $text);
	}
	
 	function debr( $text ) {
 	/** Changes <br /> with \n. **/
		return str_replace( '<br />', "\n", $text);
	}

 	function correct_nls( $text ) {
 	/** Changes \n\r with \n. **/
		return str_replace( "\r", "", $text);
	}
	
	function nice_money( $value, $currency_symbol=' EUR', $currency_symbol_front='', $roundTo=2) {
 	/** Nicely prints money values. Like 12,000.00 SIT. **/
		$value = (string) round($value, $roundTo);
		
		$value = str_replace(',', '.', $value);
		
		if (!strstr($value, '.')) {
			$value .= ".00";
		}

		$value = strrev($value);				// obrnemo 00.01103
		
		if (strpos($value, ',') == 1) {				// in case of 0.02112 add on 0
			$value = "0" . $value;
		}

		
		$result = substr($value,0, 3);			// damo not 00.
		
		for ( $i = 3; $i < strlen($value) ; $i++ ){
			if (((($i) % 3)==0) & ( $i > 3) & ( $i < strlen($value))) {	//ce je deljiva s tri in ni 3 in ni tazadnja
				$result .= ",";
			}	
			$result .= $value{$i};			//dodamo crko na i mestu
		}
		$value = strrev($result);				// obrnemo nazaj
		return $currency_symbol_front.$value.$currency_symbol;				// dodamo SIT $ ..
	}
	
	function phrase( $number, $phrases ) {
	/** Generate 0 appless, 1 apple, 2 appless  **/
		if ( $number <= (count($phrases) - 1))
			$right_phrase = $number;
		else 
			$right_phrase = count($phrases) - 1;
		return str_replace ( '{{}}', $number , $phrases[$right_phrase]);
	}
	
	function smartContact( $contact ){
	/** If contact looks like mail create link , else display it. **/
		if (Format::is_email($contact))
			return "<a href='mailto:$contact'>$contact</a>";
		else
			return $contact;
	}

	function markBeginning($text, $tag='b'){
		$text = trim($text);
		$position = strpos($text, ' ', 3);
		return "<$tag>".substr($text, 0, $position) . "</$tag>" . substr($text, $position);
	}

	function generateMagicId($type='time'){
		switch ($type) {
			case 'munique':
				//$id = getUniqueID();
				break;
			case 'time':
				$id = strval(time());
				break;
			case 'ftime':
				$id = date('Y_M_D_h_i_s');
				break;
			default:
				trigger_error ("magic($magic) attr must be munique, time, ftime", E_USER_ERROR); 
		}		
		return $id;
	} 

	function generateRandomId($len=40) {
		$vid = '';
		General::srand_once();
		for ($i=0; $i<$len; $i++)
			$vid .= rand(0,9);
		return $vid;
	}
	
	function pathToCrumbs( $strPath ){
		/** /a/b/c/ to array(0 => array( name => '/', link => '/'), 1 => array( name => 'a', link => '/a/')..)  **/
		$paths = split('/', $strPath);
		if ($paths[count($paths)-1] == '') $paths = array_splice($paths, 0, -1);
		$results = array(0 => array('name' => '/', 'link' => '/'));
		for($i=1; $i<count($paths); $i++){
			$results[$i]['name'] = $paths[$i];
			$results[$i]['link'] = $results[$i-1]['link'].$paths[$i].'/';
		}
		return $results;	  
	} 
			
	function ForwardLineMark($text, $mark='> '){
		$lines = explode("\n", $text);
		foreach ($lines as $key => $line) $lines[$key] = $mark.$line;
		return implode('', $lines); 
	}
	
	function fixUrl($url){
		//adds http if needed , leave ftp or https alone so we don't loose info about that 
		if (substr($url, 0, 4) == 'http')
			return $url;
		else 
			return "http://$url";
	}

	function beautifyUrl($url){
		//remove / at the end if there
		if (substr($url, -1, 1) == '/')
			$url = substr($url, 0, -1);
		//removes http if there
		if (substr($url, 0, 7) == 'http://')
			$url = substr($url, 7);
		return $url;
	}
		
	function qHelp($text){
		return "<span class='quick-help'>?</span>";
	}
	
	function toSloDate($sqldate){
		$e = explode('-', $sqldate);
		if (count($e) == 3)
		{
			return "{$e[2]}.{$e[1]}.{$e[0]}";	
		}
		else
		{
			return '';
		}
	}

	function toSloDateShort($sqldate){
		$e = explode('-', $sqldate);
		if (count($e) == 3)
		{
			return "{$e[2]}.{$e[1]}.".substr($e[0], -2);	
		}
		else
		{
			return '';
		}
	}
	
	function toSloDateTime($sqldate, $noSeconds=true){
		$s = explode(' ', $sqldate);
		if (count($s) == 2)
		{
			$e = explode('-', $s[0]);
			$t = explode(':', $s[1]);
			if (count($e) == 3 AND count($t) == 3)
			{
				$secStr = "";
				if (!$noSeconds) $secStr = ":{$t[2]}";
				return "{$e[2]}.{$e[1]}.{$e[0]} {$t[0]}:{$t[1]}$secStr";	
			}
		}
		return '';
	}

	function fromSloDate($date){
		$e = explode('.', $date);
		if (count($e) == 3)
		{
			return sprintf("%04d-%02d-%02d", $e[2], $e[1], $e[0]);
		}
		else
		{
			return '#noQuote#NULL';
		}
	}

	function html_print_r($text, $return=false)
	{
		if ($return)
			return Format::br_plus(addslashes(Format::nbsp(htmlentities(print_r($text, true)), ENT_COMPAT)));
		else
			echo Format::br_plus(addslashes(Format::nbsp(htmlentities(print_r($text, true)), ENT_COMPAT)));
	}	
	
	function getDomainFromEmail($email)
	{
		return substr($email, strpos($email, '@')+1);		
	}

	function getUserFromEmail($email)
	{
		return substr($email, 0, strpos($email, '@'));
	}

	function dictToGETParams($array, $startWith='?', $delim='&amp;')
	{
		$r = array();
		foreach ($array as $key => $val)
		{
			$r[] = "$key=$val";
		}
		//print_r($r);
		return $startWith . implode($delim, $r);
	}
	
	//Dodajam funkcije za slovenske datume. $date mora biti v obliki timestamp.
	
	public function getSloDay($date) {
		
		$days = array('nedelja', 'ponedeljek', 'torek', 'sreda', 'četrtek', 'petek', 'sobota');
		return $days [date('w', strtotime($date))];
		
	}
	
	public function getSloMonth($date) {
		
		$days = array(1=>'januar', 'februar', 'marec', 'april', 'maj', 'junij', 'julij', 'avgust', 'september', 'oktober', 'november', 'december');
		return $days[date('n', strtotime ($date))];
		
	}
	
	public function encodeHtmlSpecialChars($data, $which=array())
	//$data -- array to encode
	//$which -- which of the keys from that array to encode
	{
		foreach($data as $key => $item)
			if (in_array($key, $which) or !count($which))
				$data[$key] = htmlspecialchars($item, ENT_QUOTES);
		return $data;
	}
	
	function getSmartDateTime($mySqlDate)
	{
		//todo -- make it more format flexible and even nicer
		$parts1 = explode(' ', $mySqlDate);
		$time = substr($parts1[1], 0, strrpos($parts1[1], ':'));
		if ($parts1[0] == date('Y-m-d'))
		{
			return 'today '.$time;
		}
		return $parts1[0].' '.$time;
	}
	
	function beautifyConstant($t)
	{
		return ucfirst(str_replace('_', ' ', $t));
	}
	
}	 

?>
