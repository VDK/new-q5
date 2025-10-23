<?php
include_once __DIR__ . '/../lib/functions.php';
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

	//MVC search result

    /** Fast search: MediaWiki search returns QIDs for humans only */
		public static function searchHumanQids(string $term, int $limit = 20): array {
		    $term = trim($term);
		    if ($term === '') return [];

		    // escape just the double quotes for phrase search
		    $phrase = str_replace('"', '\"', $term);

		    $vars = [
		        'action'  => 'query',
		        'list'    => 'search',
		        'utf8'    => 'true',
		        'format'  => 'json',
		        'srlimit' => $limit,
		        // exact phrase + human constraint
		        'srsearch'=> 'haswbstatement:P31=Q5 "' . $phrase . '"',
		    ];
		    $resp = wd_api_query($vars);

		    $qids = [];
		    foreach (($resp['query']['search'] ?? []) as $r) {
		        $t = $r['title'] ?? '';
		        if ($t) $qids[] = $t;
		    }
		    return array_values(array_unique($qids));
		}


 /** Batch-fetch claims/labels via SPARQL for given QIDs */
// Person.php

		// small helper for QID extraction
		protected static function extractQidFromUri(?string $uri): ?string {
		    if (!$uri) return null;
		    return preg_match('~/entity/(Q\d+)~', $uri, $m) ? $m[1] : null;
		}

		/**
		 * Hook: subclasses (e.g. IMDB) can canonicalize/condense profession labels here.
		 * Default: no-op.
		 */
		protected static function normalizeProfessions(array $labels): array {
		    return $labels;
		}

		/**
		 * One-shot SPARQL + aggregation + enrichment.
		 * Returns per-QID rows with:
		 *  - itemLabel
		 *  - occupation/country labels (joined for UI) + *_qids arrays
		 *  - dates, image + thumb, imdb
		 *  - descriptionEn (existing) and two suggestions (demonym/noun)
		 */
		public static function fetchMetadata(array $qids, array $extPids = []): array
		{
		    $qids = array_values(array_unique(array_filter($qids)));
		    if (!$qids) return [];

		    // sanitize PIDs defensively
		    $extPids = array_values(array_unique(array_filter(array_map(function($p){
		        $p = strtoupper(trim((string)$p));
		        return preg_match('/^P\d+$/', $p) ? $p : null;
		    }, $extPids))));

		    // dynamic SELECT and OPTIONALs for external IDs
		    $selectExt = '';
		    $optionalExt = '';
		    foreach ($extPids as $pid) {
		        $var = 'v' . $pid; // e.g., vP345
		        $selectExt   .= " ?$var";
		        $optionalExt .= " OPTIONAL { ?item wdt:$pid ?$var. }\n";
		    }

		    $sparql = "
		SELECT ?item ?itemLabel ?occupation ?occupationLabel ?country ?countryLabel ?dob ?dod ?image ?desc $selectExt
		WHERE {
		  VALUES ?item { wd:" . implode(' wd:', $qids) . " }
		  OPTIONAL { ?item wdt:P106 ?occupation. }
		  OPTIONAL { ?item wdt:P27  ?country. }
		  OPTIONAL { ?item wdt:P569 ?dob. }
		  OPTIONAL { ?item wdt:P570 ?dod. }
		  OPTIONAL { ?item wdt:P18  ?image. }
		  $optionalExt
		  OPTIONAL { ?item schema:description ?desc FILTER (LANG(?desc) = 'en') }
		  SERVICE wikibase:label { bd:serviceParam wikibase:language 'en,mul'. }
		}";
		    $resp = sparqlQuery($sparql);


		    $grouped = [];
		    foreach (($resp['results']['bindings'] ?? []) as $b) {
		        $qid = str_replace('http://www.wikidata.org/entity/', '', $b['item']['value']);

		        if (!isset($grouped[$qid])) {
		            $grouped[$qid] = [
		                'qitem'       => $qid,
		                'itemLabel'   => $b['itemLabel']['value'] ?? '',
		                'occupation'  => [],
		                'country'     => [],
		                'occupation_qids'  => [],
		                'nationality_qids' => [],
		                'dateOfBirth' => null,
		                'dateOfDeath' => null,
		                'image'       => null,
		                'imageThumb'  => null,
		                'external'    => [],     // e.g. [ 'P345' => ['nm1234567'] ]
		                'descriptionEn' => null,
		                'description_suggest_en_demonym' => null,
		                'description_suggest_en_noun'    => null,
		            ];
		        }

		        // labels for UI
		        if (!empty($b['occupationLabel']['value'])) {
		            $grouped[$qid]['occupation'][] = $b['occupationLabel']['value'];
		        }
		        if (!empty($b['countryLabel']['value'])) {
		            $grouped[$qid]['country'][] = $b['countryLabel']['value'];
		        }

		        // QIDs
		        if (!empty($b['occupation']['value'])) {
		            if ($oq = self::extractQidFromUri($b['occupation']['value'])) {
		                $grouped[$qid]['occupation_qids'][] = $oq;
		            }
		        }
		        if (!empty($b['country']['value'])) {
		            if ($cq = self::extractQidFromUri($b['country']['value'])) {
		                $grouped[$qid]['nationality_qids'][] = $cq;
		            }
		        }

		        // dates
		        if (!empty($b['dob']['value'])) {
		            $grouped[$qid]['dateOfBirth'] = substr($b['dob']['value'], 0, 10);
		        }
		        if (!empty($b['dod']['value'])) {
		            $grouped[$qid]['dateOfDeath'] = substr($b['dod']['value'], 0, 10);
		        }

		        // image + thumb
		        if (!empty($b['image']['value'])) {
		            $fname = $b['image']['value']; // P18 literal
		            $grouped[$qid]['image']      = self::extractCommonsFilename($fname);
		            $grouped[$qid]['imageThumb'] = self::commonsThumbUrl($fname, 120);
		        }

		        // current English description
		        if (!empty($b['desc']['value'])) {
		            $grouped[$qid]['descriptionEn'] = $b['desc']['value'];
		        }

		         


			   // external IDs (dynamic, no normalization — return WD literal as-is)
					foreach ($extPids as $pid) {
					    $var = 'v' . $pid;            // e.g. vP345
					    if (isset($b[$var]['value'])) {
					        $val = $b[$var]['value']; // exact WD value
					        if (!isset($grouped[$qid]['external'][$pid])) {
					            $grouped[$qid]['external'][$pid] = [];
					        }
					        if (!in_array($val, $grouped[$qid]['external'][$pid], true)) {
					            $grouped[$qid]['external'][$pid][] = $val;
					        }
					    }
					}

		    }

		    // de-dup arrays
		    foreach ($grouped as &$g) {
		        $g['occupation']       = array_values(array_unique($g['occupation']));
		        $g['country']          = array_values(array_unique($g['country']));
		        $g['occupation_qids']  = array_values(array_unique($g['occupation_qids']));
		        $g['nationality_qids'] = array_values(array_unique($g['nationality_qids']));
		       	$countryLabel = $g['country'][0] ?? null;
						$natInfo   = is_string($countryLabel) ? self::inferNationalityFromLabel($countryLabel)
						                                      : ['demonym'=>null,'natPhrase'=>null];
						$nat       = $natInfo['demonym']  ?? null;
						$natPhrase = $natInfo['natPhrase']?? null;

						[$d1, $d2] = self::buildDescriptions($nat, $natPhrase, $g['occupation']);
						$g['description_suggest_en_demonym'] = $d1;
						$g['description_suggest_en_noun']    = $d2;
		    }
		    unset($g);

		  

		    return array_values($grouped);
		}

		private static function pickOne(?array $arr): ?string {
		    if (!$arr) return null;
		    foreach ($arr as $v) { $v = trim((string)$v); if ($v !== '') return $v; }
		    return null;
		}

		/** Return a normalized Commons filename (no scheme/host, no Special:FilePath, no "File:") */
		protected static function extractCommonsFilename(?string $s): ?string {
		    if (!$s) return null;
		    $s = trim($s);
		    if ($s === '') return null;

		    // Decode once if percent-encoded (handles %28, %29, etc.). Avoid turning + into space.
		    $s = rawurldecode($s);

		    // If it's a full URL with Special:FilePath or title=File:...
		    if (preg_match('~[?&]title=File:([^&#]+)~i', $s, $m)) {
		        $s = $m[1];
		    } elseif (preg_match('~(?:Special:FilePath/|File:)([^?#]+)~i', $s, $m)) {
		        $s = $m[1];
		    }

		    // Strip possible File: prefix and normalize spaces to underscores (Commons canonical)
		    $s = preg_replace('~^File:~i', '', $s);
		    $s = str_replace(' ', '_', $s);

		    return $s !== '' ? $s : null;
		}

		/** Build a reliable Commons thumbnail URL for a filename */
		protected static function commonsThumbUrl(?string $filename, int $width = 220): ?string {
		    $title = self::extractCommonsFilename($filename);
		    if (!$title) return null;

		    return 'https://commons.wikimedia.org/w/thumb.php?f='
		         . rawurlencode($title)
		         . '&w=' . intval($width);
		}



		protected static function inferNationalityFromLabel(?string $loc): array
{
    if (!$loc) {
        return ['demonym'=>null,'phrase'=>null,'natPhrase'=>null,'country'=>null,'country_qid'=>null,'nationality_qid'=>null];
    }

    // If WD gave just "Netherlands", keep it; if "Amsterdam, Netherlands", take last
    $parts = array_map('trim', explode(',', $loc));
    $last  = end($parts) ?: '';

    // normalize spacing + lowercase key for lookups
    $last = preg_replace('/\s+/u', ' ', $last);
    $key  = mb_strtolower($last, 'UTF-8');

    // Canonicalize common synonyms (all-lowercase keys)
    static $canonical = [
        'usa' => 'United States',
        'u.s.a.' => 'United States',
        'u.s.' => 'United States',
        'us' => 'United States',
        'united states of america' => 'United States',
        'uk' => 'United Kingdom',
        'u.k.' => 'United Kingdom',
        'britain' => 'United Kingdom',
        'great britain' => 'United Kingdom',
        'the netherlands' => 'Netherlands',
        'kingdom of the netherlands' => 'Netherlands',
    ];
    $country = $canonical[$key] ?? $last;

    // Demonyms (all-lowercase keys)
    static $demonym = [
        'netherlands' => 'Dutch',
        'kingdom of the netherlands' => 'Dutch',
        'united states' => 'American',
        'united states of america' => 'American',
        'united kingdom' => 'British',
        'england' => 'English',
        'scotland' => 'Scottish',
        'wales' => 'Welsh',
        'ireland' => 'Irish',
        'germany' => 'German',
        'france' => 'French',
        'italy' => 'Italian',
        'spain' => 'Spanish',
        'portugal' => 'Portuguese',
        'belgium' => 'Belgian',
        'sweden' => 'Swedish',
        'norway' => 'Norwegian',
        'denmark' => 'Danish',
        'finland' => 'Finnish',
        'poland' => 'Polish',
        'czech republic' => 'Czech',
        'czechia' => 'Czech',
        'slovakia' => 'Slovak',
        'switzerland' => 'Swiss',
        'austria' => 'Austrian',
        'canada' => 'Canadian',
        'australia' => 'Australian',
        'new zealand' => 'New Zealander',
        'japan' => 'Japanese',
        'china' => 'Chinese',
        'india' => 'Indian',
        'brazil' => 'Brazilian',
        'mexico' => 'Mexican',
        'argentina' => 'Argentine',
        'russia' => 'Russian',
        'ukraine' => 'Ukrainian',
        'turkey' => 'Turkish',
        'iran' => 'Iranian',
        'israel' => 'Israeli',
        'south africa' => 'South African',
    ];

    $nat       = $demonym[$key] ?? null;  // ← use the normalized key
    static $takes_article = ['United States','United Kingdom','Netherlands','Philippines','Czech Republic','United Arab Emirates','Dominican Republic','Democratic Republic of the Congo','Republic of the Congo','Gambia','Bahamas','Maldives','Seychelles'];

    $article   = in_array($country, $takes_article, true) ? 'the ' : '';
    $phrase    = "from {$article}{$country}";

    // optional QIDs (unchanged)
    static $qid_country = [ 'United States'=>'Q30','United Kingdom'=>'Q145','Netherlands'=>'Q55','France'=>'Q142','Germany'=>'Q183','Italy'=>'Q38','Spain'=>'Q29','Canada'=>'Q16','Australia'=>'Q408','Ireland'=>'Q27','Philippines'=>'Q928','Czech Republic'=>'Q213','United Arab Emirates'=>'Q878','Dominican Republic'=>'Q786','Democratic Republic of the Congo'=>'Q974','Republic of the Congo'=>'Q971','Gambia'=>'Q1005','Bahamas'=>'Q778','Maldives'=>'Q826','Seychelles'=>'Q1042' ];
    static $qid_nationality = [ 'United States'=>'Q30','United Kingdom'=>'Q145','Netherlands'=>'Q29999','France'=>'Q142','Germany'=>'Q183','Italy'=>'Q38','Spain'=>'Q29','Canada'=>'Q16','Australia'=>'Q408','Ireland'=>'Q27','Philippines'=>'Q928','Czech Republic'=>'Q213','United Arab Emirates'=>'Q878','Dominican Republic'=>'Q786','Democratic Republic of the Congo'=>'Q974','Republic of the Congo'=>'Q971','Gambia'=>'Q1005','Bahamas'=>'Q778','Maldives'=>'Q826','Seychelles'=>'Q1042' ];

    $country_qid     = $qid_country[$country] ?? null;
    $nationality_qid = $qid_nationality[$country] ?? $country_qid;

    return [
        'demonym'         => $nat,
        'phrase'          => $phrase,
        'natPhrase'       => $phrase,  // compat
        'country'         => $country,
        'country_qid'     => $country_qid,
        'nationality_qid' => $nationality_qid,
    ];
}




	protected static function buildDescriptions(?string $nat, ?string $nat_phrase, array $profs): array
	{
	    // sanitize professions (drop empties, dedupe case-insensitively)
	    $clean = [];
	    foreach ($profs as $p) {
	        $p = trim((string)$p);
	        if ($p === '') continue;
	        $clean[mb_strtolower($p)] = $p; // preserve original casing
	    }
	    $profs = array_values($clean);

	    // A) no professions, but nationality present → OK
	    if (!$profs && ($nat || $nat_phrase)) {
	        $demonym = $nat ?: null;          // keep caps ("Dutch")
	        $noun    = $nat_phrase ?: null;    // keep caps in country ("from the Netherlands")
	        return [$demonym, $noun];
	    }

	    // B) nothing
	    if (!$profs) return [null, null];

	    // C) have professions; nationality optional
	    $uniq = array_values(array_unique($profs));
	    $last = array_pop($uniq);
	    $list = $uniq ? implode(', ', $uniq) . ' and ' . $last : $last;

	    // demonym keeps caps; professions typically already lowercase in your maps
	    $demonym = $nat ? "$nat $list" : $list;

	    // noun: lowercase ONLY the profession list, then append nat phrase as-is
	    $listLower = mb_strtolower($list);
	    $noun = $nat_phrase ? ($listLower . ' ' . $nat_phrase) : $listLower;

	    return [$demonym, $noun];
	}




}



?>