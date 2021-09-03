<?php
include_once 'reference.php';
/**
 * 
 */
class legacyRef extends reference
{
	const LEGACY = 'https://www.legacy.com/link.asp?i=gb';
	private $legacyID = null;
	function __construct($url, $name)
	{
		if(filter_var($url, FILTER_VALIDATE_URL)){
			$authors  = array();
			self::setURL($url);
			self::setTitle($name);
			$response = file_get_contents($url);
			var_dump($response);die;
		}
	}

	public function getPublisherQID(){
		if ($this->publisherQID == null && $this->url != null){
			if (preg_match('/legacy\.com\/(guestbooks|obituaries)\/[a-z\-]+\//', $url, $matches)){

							
				$query = 'SELECT ?qid WHERE {
					{?item wdt:P8507 "'.$url.'" }
					BIND(REPLACE(STR(?item), "http://www.wikidata.org/entity/", "") AS ?qid)	}LIMIT 1';
				$data = sparqlQuery($query);
				foreach ($data['results']['bindings'] as $item) {
					$this->publisherQID =  $item['qid']['value'];
				}
			}
		}
		return $this->publisherQID;
	}

	public function getQS(){
		if ($this->url != null){
			$qs = "";
			if ($this->title != null){
				$qs .= '|S1476|'.$this->lang.':"'.$this->title.'"';
			}
			if (self::getPublisherQID() != null){
				//P123 = publisher
				$qs .= '|S123|'.$this->publisherQID;
			}
			$qs .= "|S407|".$this->langQID;
			
			if ($this->pubdate != null){
				//P577 = publication date
				$qs .= '|S577|+'.date("Y-m-d",$this->pubdate).'T00:00:00Z/11';
			}
			//P813 = retrieved
			$qs .= '|S813|+'.date('Y-m-d', strtotime("today")).'T00:00:00Z/11'; 
			$qs .= '|S854|"'.$this->url.'"';

			return $qs;
		}
		return null;
	}

	public function getDescribedAtUrlQS(){
	}
}

function getHeaders($url){
	$curl = curl_init();
	curl_setopt_array($curl, array(    
	    CURLOPT_URL => $url,
	    CURLOPT_HEADER => true,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_NOBODY => true));

	$header = explode("\n", curl_exec($curl));
	curl_close($curl);

	return $header;
}
?>