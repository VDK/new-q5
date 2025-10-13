<?php



function wd_api_query(array $params) {
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query($params);

    static $context = null;
    if ($context === null) {
        $opts = [
            "http" => [
                "header" => "User-Agent: New-Q5/2.0 (https://veradekok.nl/contact)\r\n"
            ]
        ];
        $context = stream_context_create($opts);
    }

    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception("Wikidata API request failed: $url");
    }

    return json_decode($response, true);
}

function send_json($payload, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}





function getLabel($qid, &$label_cache, $lang = "en") {
    // Check cache
    if (isset($label_cache[$lang][$qid])) {
        return $label_cache[$lang][$qid];
    }
    //prepare a cache for "mul"
    if (!isset($label_cache['mul'])) {
        $label_cache['mul'] = [];
    }

    // Query Wikidata API
    $query = wd_api_query([
        'format' => 'json',
        'action' => 'wbgetentities',
        'props'  => 'labels',
        'ids'    => $qid
    ]);

    $entities = $query['entities'][$qid]['labels'] ?? [];

    if (isset($entities[$lang]['value'])) {
        $label = $entities[$lang]['value'];
    } elseif (isset($entities['mul']['value'])) {
        $label = $entities['mul']['value'];
    } else {
        $label = $qid; // fallback to QID
    }

    // Store in cache in both languages
    $label_cache[$lang][$qid] = $label;
    $label_cache['mul'][$qid] = $label;

    return $label;
}


function country_substitute($countries){
	$new_countries = array();
	foreach ($countries as $country){
		switch ($country) {
			case 'Kingdom of the Netherlands':
				array_push($new_countries, "the Netherlands");
				break;
			case 'United States of America':
				array_push($new_countries, "the USA");
				break;
			default:
				array_push($new_countries, $country);
				break;
		}
	}
	return $new_countries;
}

function get_date_string($datavalue){
	//case 9
	$precision_cut = 4;
	switch($datavalue["precision"]){
		case 10:
		$precision_cut = 6;
		break;
		case 11:
		$precision_cut = 10;
		break;
	}
	return substr($datavalue["time"], 1, $precision_cut);
}

$search_vars = array(
	'action'=>'query',
	'list'=>'search',
	'utf8'=>'true',
	'format'=>'json',
	'srlimit'=>'20');


$wbgetclaims = array('action'=> 'wbgetclaims','format'=>'json');

$label_cache = array("en" => array());

if (isset($_GET['srsearch'])) {
    $search_vars = [
        'action'  => 'query',
        'list'    => 'search',
        'utf8'    => 'true',
        'format'  => 'json',
        'srlimit' => '20',
        // keep your current quoting; remove the quotes if you prefer plain text search
        'srsearch'=> "haswbstatement:P31=Q5 '" . trim($_GET['srsearch']) . "'",
    ];

    $resp  = wd_api_query($search_vars);
    $query = $resp['query'] ?? null;
    if (!$query) {
        die("Request failed");
    }

	if ($query['searchinfo']['totalhits'] >= 1){
		$results = array();
		foreach ($query['search'] as $key => $search_result) {
			$result = array(
			'qitem'=> $search_result['title'],
			'itemLabel' => getLabel($search_result['title'], $label_cache));

			$claimsResp = wd_api_query([
			    'action' => 'wbgetclaims',
			    'format' => 'json',
			    'entity' => $search_result['title']
			]);
			$claims = $claimsResp['claims'] ?? [];

			
			$occupations = [];
			if (!empty($claims['P106'])) {
			    foreach ($claims['P106'] as $occ) {
			        $occupations[] = getLabel($occ['mainsnak']['datavalue']['value']['id'], $label_cache);
			    }
			}
			$occupations = array_unique($occupations);
			$result['occupation'] = implode("/&shy;", $occupations);

			$countries = [];
			if (!empty($claims['P27'])) {
			    foreach ($claims['P27'] as $c) {
			        $countries[] = getLabel($c['mainsnak']['datavalue']['value']['id'], $label_cache);
			    }
			}
			$countries = country_substitute(array_unique($countries));
			$result['country'] = implode("/&shy;", $countries);

			if (isset($claims['P569'])){
				$result['dateOfBirth'] = get_date_string($claims['P569'][0]["mainsnak"]["datavalue"]["value"]);
			}
			if (isset($claims['P570'])){

				$result['dateOfDeath'] = get_date_string($claims['P570'][0]["mainsnak"]["datavalue"]["value"]);
			}
			if (isset($claims['P18'])){
				$result['image'] = $claims['P18'][0]["mainsnak"]["datavalue"]["value"];
			}
			
			$results[] = $result;
		}
		if (isset($results) && count($results) > 0){
			echo json_encode($results);
		}
		else{
			echo json_encode('nee');
		}
	}
	else{
		echo json_encode('nee');
	}
}




// ---- P+V picker endpoints ----
// --- New-Q5: P+V picker endpoints ---

// ---- New-Q5: P+V picker endpoints ----

// Property search (only item-valued or external-id)
if (isset($_GET['pv']) && $_GET['pv'] === 'propsearch') {
    $q    = trim($_GET['q'] ?? '');
    $lang = preg_replace('/[^a-z\-]/i', '', $_GET['lang'] ?? 'en');

    if ($q === '') send_json([]);

    // 1) search properties
    $s = wd_api_query([
        'action'   => 'wbsearchentities',
        'format'   => 'json',
        'type'     => 'property',
        'search'   => $q,
        'language' => $lang,
        'uselang'  => $lang,
        'limit'    => 20
    ]);
    $hits = $s['search'] ?? [];
    if (!$hits) send_json([]);

    // 2) fetch datatypes for those PIDs (single batch)
    // Note: arrow fn requires PHP 7.4+. If older, use an anonymous function.
    $ids = array_values(array_unique(array_filter(array_map(fn($h) => $h['id'] ?? '', $hits))));
    $e   = wd_api_query([
        'action' => 'wbgetentities',
        'format' => 'json',
        'ids'    => implode('|', $ids),
        'props'  => 'datatype'
    ]);
    $entities = $e['entities'] ?? [];

    // keep only wikibase-item or external-id
    $out = [];
    foreach ($hits as $h) {
        $pid = $h['id'] ?? '';
        if (!$pid) continue;
        $dt = $entities[$pid]['datatype'] ?? '';
        if ($dt === 'wikibase-item' || $dt === 'external-id') {
            $out[] = [
                'id'          => $pid,
                'label'       => $h['label'] ?? '',
                'description' => $h['description'] ?? '',
                'datatype'    => $dt
            ];
        }
    }
    send_json($out);
}

// Item (Q) search
if (isset($_GET['pv']) && $_GET['pv'] === 'itemsearch') {
    $q    = trim($_GET['q'] ?? '');
    $lang = preg_replace('/[^a-z\-]/i', '', $_GET['lang'] ?? 'en');

    if ($q === '') send_json([]);

    $s = wd_api_query([
        'action'   => 'wbsearchentities',
        'format'   => 'json',
        'type'     => 'item',
        'search'   => $q,
        'language' => $lang,
        'uselang'  => $lang,
        'limit'    => 20
    ]);

    $out = [];
    foreach (($s['search'] ?? []) as $h) {
        $out[] = [
            'id'          => $h['id'] ?? '',
            'label'       => $h['label'] ?? '',
            'description' => $h['description'] ?? ''
        ];
    }
    send_json($out);
}




?>