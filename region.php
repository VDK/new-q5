<?php
/**
 * 
 */
include_once 'sparqlQuery.php';
class Region
{
	private $qid = null;
	private $parts = null;
	private $parent = null;
	private $nationality = null;
	private $label = "";
	function __construct($qid, $label = null)
	{
		self::setQID($qid);
		$this->label = strip_tags($label);
	}

	public function setQID($value){
		if (preg_match('/^Q\d+$/', $value)){
			$this->parts = null;
			$this->parent = null;
			$this->label = "";
			$this->nationality = null;
			$this->qid = $value;
		}
	}
	public function getQID(){
		return $this->qid;
	}
	public function setLabel($value){
		$this->label = strip_tags($value);
	}
	public function getLabel(){
		if ( $this->qid != null && $this->label == null){
			$query = 'SELECT ?itemLabel WHERE  {
			   BIND(wd:'.$this->qid.' AS ?item).
			   SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
			}LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as  $value) {
				$this->label = $value['itemLabel']['value'];
			}
		}
		return $this->label;
	}
	
	public function getParts(){
		if($this->qid != null && $this->parts == null){
			$this->parts = array();
			$this->parentQID = null;
			$query = 'SELECT ?qid ?itemLabel ?hasPartLabel ?parentQID WHERE  {
			   BIND(wd:'.$this->qid.' AS ?item).
			   ?item wdt:P527 ?hasPart.
			   ?hasPart rdfs:label ?label.FILTER (langMatches( lang(?label), "en" )).FILTER(CONTAINS(STR(?label), " in "))
			   BIND( REPLACE( str(?hasPart),\'http://www.wikidata.org/entity/\', \'\') as ?qid)
			   OPTIONAL {
				 ?item wdt:P361 ?partOf.
				 ?partOf wdt:P31 wd:Q3241045 #// parent = instance of "desease outbreak"
				 BIND(REPLACE(STR(?partOf), "http://www.wikidata.org/entity/", "") AS ?parentQID)}
			   SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
			}';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as  $value) {
				$label = trim(preg_replace('/^.+? in (the)?(.+)$/', "$2", $value['hasPartLabel']['value']));
				$this->parts[$value['qid']['value']] = $label;
				$this->label = $value['itemLabel']['value'];
				if (isset($value['parentQID']['value'])){
					$this->parentQID = $value['parentQID']['value'];
				}
			}
			
			asort($this->parts);
		}
		return $this->parts;
	}
	public function getShortLabel(){
		return preg_replace('/^(.+ in (the )?)(.+)$/', "$3", self::getLabel());
	}

	public function getParentQID(){
		if ($this->qid != null && $this->parent == null){
			$query = 'SELECT ?qid ?itemLabel  WHERE  {
			   BIND(wd:'.$this->qid.' AS ?item).
			   ?item wdt:P361 ?partOf.
			   ?partOf wdt:P31 wd:Q3241045 #// parent = instance of "desease outbreak"
			   BIND( REPLACE( str(?partOf),\'http://www.wikidata.org/entity/\', \'\') as ?qid)
			   SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
			} LIMIT 1';
			$data = sparqlQuery($query);
			foreach ($data['results']['bindings'] as  $value) {
				$this->parent = $value['qid']['value'];
				$this->label  = $value['itemLabel']['value'];
			}
		}
		return $this->parent;
	}
	public function getNationality($format = ''){
		if($this->qid != null){
			$this->nationality = null;
			$query = 'SELECT ?qid ?itemLabel WHERE {
			  BIND (wd:'.$this->qid.' as ?item).
			    ?item wdt:P17 ?country.
			    ?country wdt:P17 ?state.
			    ?state wdt:P31 wd:Q3624078
			    BIND( REPLACE( str(?state),\'http://www.wikidata.org/entity/\', \'\') as ?qid)
			    SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
			}';
			$data = sparqlQuery($query);
			if (count($data['results']['bindings']) == 1) {
				$this->nationality = $data['results']['bindings'][0]['qid']['value'];
				$this->label = $data['results']['bindings'][0]['itemLabel']['value'];
			}
		}
		if ($this->nationality != null && $format == 'qs'){
			return 'LAST|P27|'.$this->nationality;
		}
		return $this->nationality;
	}
}
?>