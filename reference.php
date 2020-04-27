<?php
include_once 'sparqlQuery.php';

/**
 * 
 */
class reference 
{
	private $url = null;
	private $publisherQID = null;
	private $lang  = 'en';
	private $authors = "";
	private $title   = null;
	private $pubdate = null;

	const CITOID = 'https://en.wikipedia.org/api/rest_v1/data/citation/wikibase/';
	function __construct($url='', $lang='', $authors = '', $title = '', $pubdate = ''){
		self::setURL($url);
		self::setLanguage($lang);
		self::setAuthors($authors);
		self::setTitle($title);
		self::setPubDate($pubdate);
	}
	public function setURL($value){
  		if(filter_var($value, FILTER_VALIDATE_URL)){
			$this->url = $value;
			//maybe use parse_url instead
			$main_url = preg_replace('/^((http:\/\/|https:\/\/)?([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}?)*\/.*/', "$1", $this->url);
			$query = 'SELECT ?qid WHERE {
	  			?item  wdt:P856 <'.$main_url.'>.
	  			BIND( REPLACE( str(?item), \'http://www.wikidata.org/entity/\', \'\') as ?qid)
				}
				LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as $item) {
				$this->publisherQID =  $item['qid']['value'];
			}
		}
	}
	public function getURL(){
		return $this->url;
	}
	public function setPubDate($value){
		$this->pubdate = strtotime($value);
	}
	public function getPubDate(){
		return $this->pubdate;
	}
	public function setTitle($value){
		$this->title = strip_tags($value);
	}
	public function getTitle(){
		return $this->title;
	}
	public function setLanguage($value){
		$value = strtolower(strip_tags($value));
		if (preg_match('/\w\w\w?(-\w\w\w?)?$/', $value)){
			$this->lang = $value;
		}
	}
	public function getLanguage(){
		return $this->lang;
	}
	public function setAuthors($value){
		$this->authors = trim(strip_tags($value));
	}
	public function getAuthors(){
		return $this->authors;
	}

	public function getQS(){
		if ($this->url != null){
			$qs = "";
			if ($this->title != null){
				$qs .= '|S1476|'.$this->lang.':"'.$this->title.'"';
			}
			if ($this->publisherQID != null){
				//P123 = publisher
				$qs .= '|S123|'.$this->publisherQID;
			}
			foreach (explode("|", $this->authors) as $author) {
				//P2093 author name string
				if($author != ""){
					$qs .= '|S2093|"'.$author.'"';
				}
			}
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
	public function getPublisherQID(){
		return $this->publisherQID;
	}
	public function loadCitoid(){
		if ($this->url != null){
			$authors  = array();
			$response = json_decode(file_get_contents(self::CITOID.urlencode($this->url)),true);
			if (count($response) == 1){
				$response = $response[0];
				if (isset($response['url'])){
					//this removes ?fb=tracking_ids
					$this->url = $response['url'];
				}
				if (isset($response['language'])){
					$this->lang = preg_replace('/^(\w\w\w?)-?.*/', '$1', strtolower($response['language']));
				}
				if (isset($response['title'])){
					$this->title = trim(strip_tags($response['title']));
				}
				foreach ($response['creators'] as $value) {
					//P2093 = author name string
					$authors[] = trim(strip_tags($value['firstName']." ".$value['lastName']));
				}
				$this->authors = implode("|", $authors);
				if (isset($response['date'])){
					$this->pubdate  = $response['date'];
				}
			}
		}
	}
}


?>