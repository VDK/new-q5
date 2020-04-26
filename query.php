<?php
include_once 'region.php';
//API interface for navigating up and down the regions affected by the novel coronavirus

if (isset($_GET['qid'])){
	$qid  = strip_tags($_GET['qid']);
	if (preg_match('/^Q\d+$/', $qid)){
		$region = new Region($qid);
		echo json_encode(array( 
			"qid" => $region->getQID(),
			"label"=> $region->getLabel(),
			"parent"  => $region->getParentQID(),
			"regions" => $region->getParts()));
	}		
}
?>