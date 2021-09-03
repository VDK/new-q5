<?php
include_once 'sparqlQuery.php';
/**
 * 
 */
class Person
{
	
	private $doD = null;
	private $doB = null;
	private $age = null;
	private $qid = null;
	private $description = null;
	private $fullName = null;
	private $doDAccuracy = 10; //month
	private $doBAccuracy = "APROX"; //approximate

	function __construct()
	{
		//default accuracy is set to to month so
		//in the first week of a new month the previous month should be used. 
		// $this->doD = strtotime("-5 days");
	}
	public function setDescription($value){
		$this->description = trim(strip_tags($value));
	}
	public function getDescription($format=''){
		return $this->description;
	}
	public function setDOD($value, $accuracy = 11){
		if (trim($value) != ''){
			$this->doD = strtotime($value);
			$this->doDAccuracy = $accuracy;
		}
	}
	public function getDODAccuracy(){
		return $this->doDAccuracy;
	}
	public function getDOD($format=''){
		if ( $this->doD != null && $format == 'qs' ){
		   return "P570|+".date("Y-m-d", $this->doD).'T00:00:00Z/'.$this->doDAccuracy.self::getAge('qs');
		}
		else{
		   return $this->doD;
		}
	}
	

	public function setQID($value){
		$value = trim(strip_tags($value));
		if(preg_match('/^Q\d+$/', $value)){
			$this->qid = $value;
		}
	}
	public function getQID(){
		return $this->qid;
	}
	public function setAge($value){
		$value = trim(strip_tags($value));
		if (preg_match('/^\d+$/', $value)){
			$this->age = $value;
		}

	}
	public function getAge($format=''){
		if ($this->age == null && $this->doD != null && $this->doB != null){
			$this->age =  floor( ( $this->doD - $this->doB ) / 31556926);
		}
		if ($this->age != null && $format == 'qs'){
			return  "|P3629|".$this->age.'U24564698'; //years old
		}
		else{
			return $this->age;
		}
	}
	public function setDOB($value, $accuracy = 11){
		if (trim($value) != ''){
			$this->doB = strtotime($value);
			$this->doBAccuracy = $accuracy;
		}
	}

	public function getDOBAccuracy(){
		return $this->doDAccuracy;
	}
	public function getDOB($format = ''){
		if ($this->doB == null && $this->age != null && $this->doD != null){
			$this->doB = strtotime("-".$this->age." years", $this->doD); 
		}
		if ($this->doB != null && $format == 'qs'){
			if ($this->doBAccuracy == "APROX"){
				$year = date('Y', $this->doB); 
				if ((int)date("n", $this->doB) < 7){
					$year--; 
				}

				return "P569|+".$year.'-00-00T00:00:00Z/9|P1480|Q5727902'
				.'|P1319|+'.date("Y-m-d", strtotime("-1 years +1 days", $this->doB))."T00:00:00Z/".$this->doDAccuracy.
			     '|P1326|+'.date('Y-m-d', $this->doB).'T00:00:00Z/'.$this->doDAccuracy;
			}
			else{
				return "P569|+".date('Y-m-d', $this->doB).'T00:00:00Z/'.$this->doBAccuracy;
			}
		}
		else{
			return $this->doB;
		}
	}
	public function setName($value){
		$this->fullName = trim(strip_tags($value));
	}
	public function getName($format= ''){
		if ($this->fullName != null && $format == 'qs'){
			$properties = array();
			//can't handle Dutch names
			$famname    = preg_replace('/(.+) (\p{Lu}.+)$/', "$2", $this->fullName);
			$givenNames = preg_replace('/(.+) (\p{Lu}.+)$/', "$1", $this->fullName);
			$query = 'SELECT DISTINCT ?qid WHERE {
       			VALUES ?lang { "en" }
       			BIND(STRLANG("'.trim($famname).'", ?lang) AS ?label).
       			?surname rdfs:label ?label.
       			?surname wdt:P31 ?subclass_surname.
       			?subclass_surname wdt:P279 * wd:Q101352 .
       			hint:Query hint:optimizer "None".
       			BIND( REPLACE( str(?surname), \'http://www.wikidata.org/entity/\', \'\') as ?qid)
      			}LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as $result){
				$properties['P734'][] =  $result['qid']['value'];
			}
			$givenNames = explode(" ", trim($givenNames));
			$i = 1;
			foreach ($givenNames as $givenName) {
	 			$query = 'SELECT DISTINCT ?qid ?gen WHERE {
	       			VALUES ?lang { "en" "de" "fr" }
	       			BIND(STRLANG("'.$givenName.'", ?lang) AS ?label).
	       			?givenName rdfs:label ?label.
	       			?givenName wdt:P31 ?subclass_givenName.
	       			?subclass_givenName wdt:P279 * wd:Q202444 .
	       			hint:Query hint:optimizer "None".
	       			BIND( REPLACE( str(?givenName), \'http://www.wikidata.org/entity/\', \'\') as ?qid).
					BIND( REPLACE( str(?subclass_givenName), \'http://www.wikidata.org/entity/\', \'\')  as ?gen).
	      			}LIMIT 1';
	  			$data  = sparqlQuery($query);
	  			foreach ($data['results']['bindings'] as $result){
	    			$properties['P735'][] = $result['qid']['value']."|P1545|\"".$i."\"";
	    			if ($i == 1){
	    				//set gender based on first name
	    				switch ($result['gen']['value']) {
	    					case 'Q11879590': //female given name 
	    						$properties['P21'][] = 'Q6581072|S887|Q202444';
	    						break;
	    					case 'Q12308941': //male given name 
	    						$properties['P21'][] = 'Q6581097|S887|Q202444';
	    						break;
	    				}
	    			}
	  			}
	  			$i++;
			}
			$qs = '';
			foreach ($properties as $key => $property) {
				foreach ($property as $value) {
					$qs .= $key."|".$value."\n";
				}
			}
			return $qs;
		}
		else{
			return $this->fullName;
		}
	}
}



?>