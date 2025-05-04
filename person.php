<?php
include_once 'functions.php';
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
	private $spanishSecondFamilyName = null;
	private $fullName = null;
	private $nameParts = null; // stores ['given' => [...], 'surname' => ...]
	private $assumedGender = null; // from the first given name's Wikidata item
	private $doDAccuracy = 10;
	private $doBAccuracy = "APROX";

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
			$this->doD = DateTime::createFromFormat("Y-m-d",date('Y-m-d',  strtotime($value)));
			$this->doDAccuracy = $accuracy;
		}
	}
	public function getDODAccuracy(){
		return $this->doDAccuracy;
	}
	public function getDOD($format=''){
		if ( $this->doD != null && $format == 'qs' ){
		   return "P570|+".$this->doD->format("Y-m-d").'T00:00:00Z/'.$this->doDAccuracy.self::getAge('qs');
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
		if ($this->age == null && $this->doD != null && $this->doB != null && $this->doDAccuracy == 11 && $this->doBAccuracy == 11){
			$this->age =  $this->doB->diff( $this->doD)->format('%y');
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
			$this->doB = DateTime::createFromFormat("Y-m-d",date('Y-m-d',  strtotime($value)));
			$this->doBAccuracy = $accuracy;
		}
	}

	public function getDOBAccuracy(){
		return $this->doBAccuracy;
	}
	public function getDOB($format = ''){
		if ($this->doB == null && $this->age != null && $this->doD != null){
			$this->doB = clone $this->doD;
			$this->doB->modify("-".$this->age." years"); 
		}
		if ($this->doB != null && $format == 'qs'){
			if ($this->doBAccuracy == "APROX"){
				$year = $this->doB->format("Y"); 
				//shift aproximate dob to a year earlier in the first six months of the year
				if ((int)$this->doB->format("n") < 7){
					$year--; 
				}
				$earliest = clone $this->doB;
				$earliest->modify("-1 years");
				$accuracy = ($this->doDAccuracy == 11) ? 10 : $this->doDAccuracy;
				return "P569|+".$year.'-00-00T00:00:00Z/9|P1480|Q5727902'
				.'|P1319|+'.$earliest->format("Y-m")."-00T00:00:00Z/".$accuracy.
			     '|P1326|+'.$this->doB->format('Y-m').'-00T00:00:00Z/'.$accuracy;
			}
			else{
				return "P569|+".$this->doB->format('Y-m-d').'T00:00:00Z/'.$this->doBAccuracy;
			}
		}
		else{
			return $this->doB;
		}
	}
	public function setName($value) {
	    $this->fullName = trim(strip_tags($value));

	    // Reset previously derived info
	    $this->givenNames = [];
	    $this->familyName = null;
	    $this->spanishSecondFamilyName = null;
	    $this->qidGivenNames = [];
	    $this->qidFamilyName = null;
	    $this->assumedGender = null;

	    ['given' => $givenParts, 'surname' => $family] = $this->splitNameParts();
	    $this->givenNames = $givenParts;
	    $this->familyName = $family;

	    // Handle possible Spanish-style second surname logic
	    // if (count($this->givenNames) > 1) {
	    //     $maybeSurname = $this->givenNames[count($this->givenNames) - 1];
	    //     $isGiven = $this->lookupGivenName($maybeSurname); // use your new API-based function
	    //     $maybeFam = $this->lookupFamilyName($maybeSurname);
	    //     $fam = $this->lookupFamilyName($this->familyName);

	    //     if ($maybeFam && !$isGiven) {
	    //         $this->spanishSecondFamilyName = $this->familyName;
	    //         $this->familyName = $maybeSurname;
	    //         array_pop($this->givenNames);
	    //     }
	    // }

	    // Store QIDs for given names
	    foreach ($this->givenNames as $i => $given) {
		    $result = $this->lookupGivenName($given);
		    if ($result) {
		        $this->qidGivenNames[] = $result['id'];
		        if ($i === 0) {
		            $this->assumedGender = $this->getGenderFromQID($result['id']);
		        }
		    }
		}


	    // Store family name QID
	    $famQid = $this->lookupFamilyName($this->familyName);
	    if ($famQid) {
	        $this->qidFamilyName = $famQid;
	    }
	}
protected function getGenderFromQID($qid): ?string {
    $query = <<<SPARQL
SELECT ?genderType WHERE {
  wd:$qid wdt:P31 ?genderType .
  FILTER(?genderType IN (wd:Q11879590, wd:Q12308941))
}
LIMIT 1
SPARQL;

	    $data = sparqlQuery($query);
	    $bindings = $data['results']['bindings'] ?? [];

	    if (!empty($bindings)) {
	        $uri = $bindings[0]['genderType']['value'] ?? '';

	   		if (strpos($uri, 'Q11879590') !== false) return 'Q11879590';
			if (strpos($uri, 'Q12308941') !== false) return 'Q12308941';
	    }

	    return null;
	}






	public function getGivenNames() {
	    return $this->givenNames;
	}

	public function getFamilyName() {
	    return $this->familyName;
	}


	public function getName($format = '') {
		if ($format !== 'qs') {
			return $this->fullName;
		}

		$properties = [];
		// Add given names with order qualifier (P735 + P1545)
		foreach ($this->givenNames as $i => $givenName) {
			if (!empty($this->qidGivenNames[$i])) {
				$properties['P735'][] = $this->qidGivenNames[$i] . '|P1545|"' . ($i + 1) . '"';
			}
		}

		// Add family name (P734)
		if ($this->qidFamilyName) {
			$properties['P734'][] = $this->qidFamilyName;
		}

		// Add second family name if Spanish-style detected (P1950)
		if ($this->spanishSecondFamilyName) {
			if ($qid = $this->lookupFamilyName($this->spanishSecondFamilyName)) {
				$properties['P1950'][] = $qid;
			}
		}

	


		// Convert to QuickStatements format
		$qs = '';
		foreach ($properties as $prop => $values) {
			foreach ($values as $value) {
				$qs .= $prop . '|' . $value . "\n";
			}
		}

		return $qs;
	}



	public function getGender($format = '') {
	    if ($this->assumedGender === null) {
	        return null;
	    }

	    if ($format === 'qs') {
	        if ($this->assumedGender === 'Q11879590') { // female given name
	            return 'P21|Q6581072|S887|Q69652498'; // female gender
	        } elseif ($this->assumedGender === 'Q12308941') { // male given name
	            return 'P21|Q6581097|S887|Q69652498'; // male gender
	        }
	    }

	    return $this->assumedGender;
	}



	protected function splitNameParts(): array {
		$tussenvoegsels = [
	    "de la", 'van', 'van de', 'van der', 'van den', 'de', 'den', 'der', 'ter', 'ten', 'op', 'onder', 'in', 'aan', 'te', 'tot', 'uit', 'over', 'bij', "von", "zu", "del", "di", "Ó" 
	    // add noble or regional ones like  etc.
		];
	    $parts = preg_split('/\s+/u', trim($this->fullName));
	    $total = count($parts);

	    if ($total < 2) {
	        return [
	            'given' => $parts,
	            'surname' => '',
	        ];
	    }

	    $surnameParts = [];
	    $i = $total - 1;

	    // Start with the last part as the base surname
	    $surnameParts[] = $parts[$i];
	    $i--;

	    // Try to prepend a known tussenvoegsel (1 or 2 words max)
	    while ($i >= 0) {
	        for ($len = 2; $len >= 1; $len--) {
	            if ($i - $len + 1 < 0) continue;

	            $maybe = implode(' ', array_slice($parts, $i - $len + 1, $len));
	            if (in_array(mb_strtolower($maybe), $tussenvoegsels, true)) {
	                // Prepend to surname
	                array_unshift($surnameParts, ...array_slice($parts, $i - $len + 1, $len));
	                $i -= $len;
	                break 1; // found one match; break inner loop
	            }
	        }
	        break; // stop after one tussenvoegsel match
	    }

	    $surname = implode(' ', $surnameParts);
	    $given = array_slice($parts, 0, $total - count($surnameParts));

	    return [
	        'given' => $given,              // array of given names
	        'surname' => $surname           // string for Wikidata query
	    ];
	}

	protected function lookupGivenName($name) {
	    $allowedInstanceOf = ['Q202444', 'Q11879590', 'Q12308941', 'Q3409032'];
	    $results = searchWikidataEntity($name, $allowedInstanceOf);
	    return $results[0] ?? null;
	}


	protected function lookupFamilyName($name) {
	    $allowedInstanceOf = ['Q101352', 'Q66480858']; // family name types

	    $results = searchWikidataEntity($name, $allowedInstanceOf);

	    // Prioritize exact-case match
	    usort($results, function ($a, $b) use ($name) {
	        $aLabel = $a['label'] ?? '';
	        $bLabel = $b['label'] ?? '';

	        $aExact = $aLabel === $name ? 0 : 1;
	        $bExact = $bLabel === $name ? 0 : 1;

	        return $aExact <=> $bExact;
	    });

	    foreach ($results as $result) {
	        if (!isset($result['id'])) continue;
	        return $result['id'];
	    }

	    return null;
	}




	protected function deduceGenderFromInstanceOf(array $instanceOfs): ?string {
	    if (in_array('Q11879590', $instanceOfs, true)) return 'Q11879590'; // female
	    if (in_array('Q12308941', $instanceOfs, true)) return 'Q12308941'; // male
	    return null;
	}






}



?>