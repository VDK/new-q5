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
	private $langQID = 'Q1860'; //English
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
			
			$urls = self::getHostVariations();
			
			$query = 'SELECT ?qid WHERE {';
			foreach ($urls as $url) {
				$query .= "{?item wdt:P856 <".$url."> }\n union";
			}
			$query = preg_replace("/union$/", "", $query);

			$query .= 'BIND(REPLACE(STR(?item), "http://www.wikidata.org/entity/", "") AS ?qid)	}LIMIT 1';
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
		$value = trim(strtolower(strip_tags($value)));
		if (preg_match('/\w\w\w?(-\w\w\w?)?$/', $value) && 
			!in_array($value, array("en", "eng"))){
			$this->lang = $value;
			$query = 'SELECT DISTINCT ?qid  WHERE {
                ?language wdt:P424 "'.$this->lang.'" . 
       			?language wdt:P31 ?subclass_language.
       			?subclass_language wdt:P279 * wd:Q34770 .
       			hint:Query hint:optimizer "None".
       			BIND( REPLACE( str(?language), \'http://www.wikidata.org/entity/\', \'\') as ?qid)
      			}LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as $result){
				$this->langQID = $result['qid']['value'];
			}
		}

	}
	public function getLanguage(){
		return $this->lang;
	}
	public function setAuthors($value){
		if (is_array($value)){
			$value = array_unique($value);
			$this->authors = implode("|", $value);
		}
		else{
			$this->authors = trim(strip_tags($value));
		}
		$this->authors = preg_replace('/\bCNN\b/', "", $this->authors);
		if (preg_match('/^[\p{Lu} \W]+$/', $this->authors)) {
			$this->authors = ucwords(strtolower($this->authors));
		}
	}
	public function getAuthors(){
		return $this->authors;
	}
	public function getDescribedAtUrlQS(){
		if ($this->url != null){
			$qs = "LAST|P973|\"".$this->url."\"";
			if ($this->title != null){
				$qs .= '|P1476|'.$this->lang.':"'.$this->title.'"';
			}
			if ($this->publisherQID != null){
				//P123 = publisher
				$qs .= '|P123|'.$this->publisherQID;
			}
			$qs .= "|P407|".$this->langQID;
			foreach (explode("|", $this->authors) as $author) {
				//P2093 author name string
				if($author != ""){
					$qs .= '|P2093|"'.$author.'"';
				}
			}
			if ($this->pubdate != null){
				//P577 = publication date
				$qs .= '|P577|+'.date("Y-m-d",$this->pubdate).'T00:00:00Z/11';
			}
			//P813 = retrieved
			$qs .= '|P813|+'.date('Y-m-d', strtotime("today")).'T00:00:00Z/11'; 

			return $qs;
		}
		return null;
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
			$qs .= "|S407|".$this->langQID;
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
					$this->url = $response['url'];
				}
				if (isset($response['language'])){
					self::setLanguage(preg_replace('/^(\w\w\w?)-?.*/', '$1', strtolower($response['language'])));
					
				}
				if (isset($response['title'])){
					$this->title = trim(strip_tags($response['title']));
					//whatevere's after the pipe is probably "| Local Newspaper"
					$this->title = preg_replace("/^(.+?)\|/", "$1", $this->title);
				}
				foreach ($response['creators'] as $value) {
					$author = trim(strip_tags($value['firstName']." ".$value['lastName']));
					if (strpos($author, " and ")){
						$authors = split(" and ", $author);
						$authors[] = $authors[0];
						$authors[] = $authors[1];
					}
					elseif (!strtotime($author)){
						$authors[] = $author;
					}
				}
				self::setAuthors($authors);
				
				if (isset($response['date'])){
					$this->pubdate  = $response['date'];
				}
			}
		}
	}
	private function getHostVariations(){
		$hosts = array();
		$hosts[] = parse_url($this->url)['host'];
		//host2: name without the "www."
		$host_parts = explode(".", $hosts[0]);
		array_shift($host_parts);
		if (count($host_parts) > 1){
			$hosts[]  = implode(".", $host_parts);
		}
		$combos = combos(array(array("http://", "https://"), 
							   array("www.", ""), 
							   $hosts, 
							   array("/", "")));
		$urls = array();
		foreach ($combos as $combo) {
			$url = implode("", $combo);
			if (!strpos($url, "www.www.")){
				$urls[] = $url;
			}
		}
		$urls = array_unique($urls);
		return $urls;
	}
}

/**
 * Generate all the possible combinations among a set of nested arrays.
 *
 * @param array $data  The entrypoint array container.
 * @param array $all   The final container (used internally).
 * @param array $group The sub container (used internally).
 * @param mixed $val   The value to append (used internally).
 * @param int   $i     The key index (used internally).
 *
 * creator: Fabio Cicerchia
 *
 * https://gist.github.com/fabiocicerchia/4556892
 */
function combos(array $data, array &$all = array(), array $group = array(), $value = null, $i = 0){
    $keys = array_keys($data);
    if (isset($value) === true) {
        array_push($group, $value);
    }

    if ($i >= count($data)) {
        array_push($all, $group);
    } else {
        $currentKey     = $keys[$i];
        $currentElement = $data[$currentKey];
        foreach ($currentElement as $val) {
            combos($data, $all, $group, $val, $i + 1);
        }
    }

    return $all;
}

?>