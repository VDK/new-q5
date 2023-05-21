<?php
include_once 'citoid_ref.php';


define('WD_API', 'https://www.wikidata.org/w/api.php?');

$context = stream_context_create(
    array(
        "http" => array(
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
        )
    )
);



if(isset($_GET['url'])){
	$url = urldecode(strip_tags($_GET['url']));
	$ref = new citoidRef($url);	
	$date = '';
	if ($ref->getPubDate() != null){
		$date = $ref->getPubDate()->format("Y-m-d");
	}
	echo json_encode(array(
		"url" 		=> $ref->getURL(),
		"title" 	=> $ref->getTitle(),
		"language" 	=> $ref->getLanguage(),
		"authors" 	=> $ref->getAuthors(),
		"pubdate" 	=> $date
	));

	

}


function getLabel($qid, $label_cache, $lang="en"){
	if (isset($label_cache[$lang][$qid])){
		return $label_cache[$lang][$qid];
	}
	$wbgetentities = array(

		'format'=>'json',
		'action'=>'wbgetentities',
		'props'=>'labels',
		'ids' => $qid
	);

	$query = json_decode(file_get_contents(WD_API.http_build_query($wbgetentities)), true);
	if (isset($query['entities'][$qid]['labels'][$lang])){
		$label = $query['entities'][$qid]['labels'][$lang]['value'];
	}
	else{
		$label = $qid;
	}
	$label_cache[$lang][$qid] = $label;
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

if(isset($_GET['srsearch'])){
	$search_vars['srsearch'] = "haswbstatement:P31=Q5 '".trim($_GET['srsearch'])."'";
	$query = json_decode(file_get_contents(WD_API.http_build_query($search_vars)), true)['query'];

	if ($query['searchinfo']['totalhits'] >= 1){
		$results = array();
		foreach ($query['search'] as $key => $search_result) {
			$result = array(
			'qitem'=> $search_result['title'],
			'itemLabel' => getLabel($search_result['title'], $label_cache));

			$wbgetclaims['entity'] = $search_result['title'];
			$claims = json_decode(file_get_contents(WD_API.http_build_query($wbgetclaims)), true)['claims'];
			
			$occupations = array();
			foreach ($claims['P106'] as $occupation) {
				array_push($occupations, getLabel($occupation['mainsnak']["datavalue"]["value"]['id'], $label_cache));
			}
			$occupations = array_unique($occupations);
			$result['occupation'] = implode("/&shy;", $occupations);

			$countries = array();
			foreach ($claims['P27'] as $country) {
				array_push($countries, getLabel($country['mainsnak']["datavalue"]["value"]['id'], $label_cache));
			}
			$countries = array_unique($countries);
			$countries = country_substitute($countries);
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
?>