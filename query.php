<?php
include_once 'citoid_ref.php';
const ORATOR_ENDPOINT= 'https://tools.wmflabs.org/orator-matcher/query.php';
//API interface for navigating up and down the regions affected by the novel coronavirus

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
		$date = date("Y-m-d", $ref->getPubDate());
	}
	echo json_encode(array(
		"url" 		=> $ref->getURL(),
		"title" 	=> $ref->getTitle(),
		"language" 	=> $ref->getLanguage(),
		"authors" 	=> $ref->getAuthors(),
		"pubdate" 	=> $date
	));

	

}

if(isset($_GET['srsearch'])){
	echo file_get_contents(ORATOR_ENDPOINT.'?'.http_build_query(array('srsearch' =>$_GET['srsearch'])), false, $context);
}
?>