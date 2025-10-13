<?php


include_once 'functions.php';


$labelCache = []; // shared cache for all getLabel() calls
$langPref   = ['en','mul','nl'];

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





if (isset($_GET['srsearch'])) {
    $search_vars = [
        'action'  => 'query',
        'list'    => 'search',
        'utf8'    => 'true',
        'format'  => 'json',
        'srlimit' => '20',
        'srsearch'=> "haswbstatement:P31=Q5 '" . trim($_GET['srsearch']) . "'",
    ];

    $resp  = wd_api_query($search_vars);
    $query = $resp['query'] ?? null;
    if (!$query) {
        send_json([]); // no hard die(); return empty
    }

    if (($query['searchinfo']['totalhits'] ?? 0) < 1) {
        send_json([]); // no hits
    }

    $results = [];

    foreach ($query['search'] as $search_result) {
        $qid = $search_result['title'] ?? '';
        if ($qid === '') { continue; }

        // label with nl→mul→en fallback (and per-request cache)
        $itemLabel = getLabel($qid, $labelCache, $langPref);

        // claims for occupations, country, dates, image
        $claimsResp = wd_api_query([
            'action' => 'wbgetclaims',
            'format' => 'json',
            'entity' => $qid
        ]);
        $claims = $claimsResp['claims'] ?? [];

        // Occupations (P106)
        $occupations = [];
        if (!empty($claims['P106'])) {
            foreach ($claims['P106'] as $occ) {
                $oid = $occ['mainsnak']['datavalue']['value']['id'] ?? null;
                if ($oid) {
                    $occupations[] = getLabel($oid, $labelCache, $langPref);
                }
            }
        }
        $occupations = array_unique($occupations);

        // Countries of citizenship (P27)
        $countries = [];
        if (!empty($claims['P27'])) {
            foreach ($claims['P27'] as $c) {
                $cid = $c['mainsnak']['datavalue']['value']['id'] ?? null;
                if ($cid) {
                    $countries[] = getLabel($cid, $labelCache, $langPref);
                }
            }
        }
        $countries = country_substitute(array_unique($countries));

        $result = [
            'qitem'       => $qid,
            'itemLabel'   => $itemLabel,
            'occupation'  => implode("/&shy;", $occupations),
            'country'     => implode("/&shy;", $countries),
        ];

        if (!empty($claims['P569'][0]['mainsnak']['datavalue']['value'])) {
            $result['dateOfBirth'] = get_date_string($claims['P569'][0]['mainsnak']['datavalue']['value']);
        }
        if (!empty($claims['P570'][0]['mainsnak']['datavalue']['value'])) {
            $result['dateOfDeath'] = get_date_string($claims['P570'][0]['mainsnak']['datavalue']['value']);
        }
        if (!empty($claims['P18'][0]['mainsnak']['datavalue']['value'])) {
            $result['image'] = $claims['P18'][0]['mainsnak']['datavalue']['value'];
        }

        $results[] = $result;
    }

    send_json($results);
}






// ---- P+V picker endpoints ----

// ---- New-Q5: P+V picker endpoints ----


//make it possible to load P's labels

if (!empty($_GET['ids'])) {
    $ids  = array_filter(array_map('trim', explode('|', $_GET['ids'])));
    $lang = $_GET['lang'] ?? 'en';

    $out = [];
    foreach ($ids as $id) {
        if (!preg_match('/^P\d+$/', $id)) continue;
        $label = getLabel($id, $labelCache, $lang);
        $out[] = [
            'id'       => $id,
            'label'    => $label,
            'datatype' => null // optionally fill later if you want
        ];
    }

    send_json($out);
    exit;
}



// Property search (reuse fetch_prop_meta_from_ids)
if (isset($_GET['pv']) && $_GET['pv'] === 'propsearch') {
    $qRaw = (string)($_GET['q'] ?? '');
    $q    = trim($qRaw);
    $lang = preg_replace('/[^a-z\-]/i', '', (string)($_GET['lang'] ?? 'en')) ?: 'en';

    if ($q === '' || mb_strlen($q) < 2) {
        send_json([]);
    }

    // 1) Search properties
    $search = wd_api_query([
        'action'   => 'wbsearchentities',
        'format'   => 'json',
        'type'     => 'property',
        'search'   => $q,
        'language' => $lang,
        'uselang'  => $lang,
        'limit'    => 20,
    ]);
    $hits = is_array($search['search'] ?? null) ? $search['search'] : [];
    if (!$hits) {
        send_json([]);
    }

    // 2) Extract PIDs in order (deduped)
    $seen = [];
    $ids  = [];
    foreach ($hits as $h) {
        $pid = strtoupper(trim((string)($h['id'] ?? '')));
        if ($pid === '' || !preg_match('/^P\d+$/', $pid)) continue;
        if (isset($seen[$pid])) continue;
        $seen[$pid] = true;
        $ids[] = $pid;
    }
    if (!$ids) {
        send_json([]);
    }

    // 3) Reuse your helper for datatype gating + labels
    $meta = fetch_prop_meta_from_ids($ids, $lang);
    if (!$meta) {
        send_json([]);
    }

    // 4) Merge search descriptions back in, keep meta order
    $descById = [];
    foreach ($hits as $h) {
        $pid = strtoupper(trim((string)($h['id'] ?? '')));
        if ($pid) $descById[$pid] = (string)($h['description'] ?? '');
    }

    $out = [];
    foreach ($meta as $m) { // $m = ['id','label','datatype']
        $pid = $m['id'];
        $out[] = [
            'id'          => $pid,
            'label'       => $m['label'],
            'description' => $descById[$pid] ?? '',
            'datatype'    => $m['datatype'], // already filtered to wikibase-item/external-id
        ];
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