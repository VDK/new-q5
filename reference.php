<?php
include_once 'sparqlQuery.php';

/**
 * 
 */
class reference 
{
	private $url = null;
	private $publisherQS = "";
	private $citoidQS 	 = "";

	const CITOID = 'https://en.wikipedia.org/api/rest_v1/data/citation/wikibase/';
	function __construct($value='')
	{
		if ($value != ''){
			$this->setURL($value);
		}
	}
	public function setURL($value){
  		if(filter_var($value, FILTER_VALIDATE_URL)){
			$this->url = $value;
			self::setPublisherQS();
			self::setCitoidQS();
		}
	}
	

	public function getQS(){
		if ($this->url != null){
			return $this->publisherQS.$this->citoidQS
			.'|S813|+'.date('Y-m-d', strtotime("today")).'T00:00:00Z/11' //P813 = retrieved
			.'|S854|"'.$this->url.'"';
		}
		return null;
	}
	private function setPublisherQS(){
		if ($this->url != null){
			$main_url = preg_replace('/^((http:\/\/|https:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}?)*\/.*/', "$1", $this->url);
			$query = 'SELECT ?qid WHERE {
	  			?item  wdt:P856 <'.$main_url.'>.
	  			BIND( REPLACE( str(?item), \'http://www.wikidata.org/entity/\', \'\') as ?qid)
				}
				LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as $item) {
				//P123 = publisher
				$this->publisherQS = '|S123|'.$item['qid']['value'];
			}
		}
	}
	private function setCitoidQS(){
		if ($this->url != null){
			$response = json_decode(file_get_contents(self::CITOID.urlencode($this->url)),true);
			if (count($response) == 1){
				$response = $response[0];
				$lang = 'en';
				if (isset($response['language'])){
					$lang = strtolower($response['language']);
				}
				if (isset($response['title'])){
					$this->citoidQS .= '|S1476|'.$lang.':"'.$response['title'].'"';
				}
				foreach ($response['creators'] as $value) {
					//P2093 = author name string
					$this->citoidQS .= '|S2093|"'.$value['firstName']." ".$value['lastName'].'"';
				}
				if (isset($response['date'])){
					//P577 = publication date
					$this->citoidQS .= '|S577|+'.$response['date'].'T00:00:00Z/11';
				}
			}
		}
	}
}


?>