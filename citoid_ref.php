<?php
include_once 'reference.php';
/**
 * 
 */
class citoidRef extends reference
{
	const CITOID = 'https://en.wikipedia.org/api/rest_v1/data/citation/wikibase/';
	
	function __construct($url)
	{
		if(filter_var($url, FILTER_VALIDATE_URL)){
			$authors  = array();
			$test = get_headers($url, 1)[0];
			if ($test != 'HTTP/1.1 404 Not Found'){
				self::setURL($url);
				$response = file_get_contents(self::CITOID.urlencode($url));
				$response = json_decode($response,true);
				if (count($response) == 1){
					$response = $response[0];
					// echo json_encode($response);die;
					
					if (isset($response['language'])){
						self::setLanguage($response['language']);
					}
					if (isset($response['title'])){
						self::setTitle($response['title']);
					}

					foreach ($response['creators'] as $value) {
						$author = trim(strip_tags($value['firstName']." ".$value['lastName']));
						if (!strtotime($author)){
							$authors[] = $author;
						}
					}
					self::setAuthors($authors);
					if (isset($response['date'])){
						self::setPubDate($response['date']);
					}
				}
			}
		}
	}
}
?>