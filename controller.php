<?php

include_once 'person.php';
include_once 'reference.php';
$error = '';
$qs = false;

function desribed_by_source(){
  if (isset($_COOKIE['described_by_source']) and count($_POST) == 0){
    return $_COOKIE['described_by_source'];
  }
  elseif (isset($_POST['described_by_source'])){
      return "checked";
  }
  return "unchecked";
}


$described_by_source  = desribed_by_source();
setcookie("described_by_source", $described_by_source , time() +  (86400 * 30 * 100));



// Function to handle form submission
function handle_form_submission() {
  $qs = ''; // Initialize $qs within the function scope
  global $qs;
  $reference1 = new Reference(
    $_POST['ref_url'], 
    $_POST['ref_lang'],
    $_POST['ref_authors'], 
    $_POST['ref_title'], 
    $_POST['ref_pubdate']);
  $loopDate = new DateTime();
  $today    = new DateTime();
  if ($reference1->getPubDate() != null){
    //shift "today" to pubDate
    $today = clone $reference1->getPubDate();
  }

  //handle person
  $person1 = new Person();
  $person1->setQID($_POST['person_QID']);
  $person1->setName($_POST['fullname']);
  $person1->setAge($_POST['age']);
  $person1->setDescription($_POST['description']);
  
  //set Date of Death
  $dod = trim(strip_tags($_POST['dod']));
  if ($dod != " "){
    // makes in possible to input "last friday -1 weeks"
    if (strripos("last ", $dod)) { 
        for ($days = 7; $days--;) {
            $dayOfWeek = $loopDate->modify('+1 days')->format('l');
            if (strripos($dod, $dayOfWeek) !== false) {
                if ($dayOfWeek == $today->format('l')) {
                    $person1->setDOD($today->format("Y-m-d"));
                } else {
                    $person1->setDOD('last ' . $dayOfWeek . " " . $today->format("Y-m-d"));
                }
            }
        }

        // Month handling
        for ($months = 0; $months < 11; $months++) { 
            $month = $loopDate->modify('-1 months')->format('F');
            if (strripos($dod, $month) !== false) {
                $date = new DateTime($month . " " . $today->format("Y"));
                if ($date > $today) {
                    $date->modify('-1 years');
                }
                $person1->setDOD($date->format("Y-m-d"), 10);
            }
        }

    } else {

        // Year handling
        if (preg_match('/^[21]\d{3}$/', $dod)) {
            $person1->setDOD($dod . "-01-01", 9);
        } elseif (preg_match('/\d/', $dod) && strtotime($dod) !== false) {

            $person1->setDOD($dod);
        }
          // Month and year without day handling
        for ($months = 0; $months < 11; $months++) { 
          $month = $loopDate->modify('-1 months')->format('F');
          if (preg_match('/^' . $month . '\s*[12]\d{3}$/i', $dod) ||  preg_match('/^[12]\d{3}\s*' . $month . '$/i', $dod) ){
            $person1->setDOD($dod, 10);
          }
        } 
    }
  }
  //end DOD
  //DOB
  $dob = trim(strip_tags($_POST['dob']));
  if (preg_match('/^[12]\d{3}$/', $dob)){
    $person1->setDOB($dob."-01-01", 9);
  }
  elseif(strtotime($dob) != false){
    $person1->setDOB($dob);
  }
  //reduce accuracy if only month + year:
  for ($months=0; $months < 11; $months++) { 
     $month = $loopDate->modify( '-1 months' )->format( 'F' );
     if (preg_match('/^'.$month.'\s*[12]\d{3}$/i' , $dob) || preg_match('/^[12]\d{3}\s*'.$month.'$/i' , $dob)  ){
        $person1->setDOB($dob, 10);
     }
  }

  //if age + referenced source
  if ($person1->getDOD() == null and $person1->getAge() != null and $reference1->getPubDate() != null){
    $pubdate = clone $reference1->getPubDate();
    $person1->setDOB($pubdate->modify( "-".$person1->getAge()." years")->format("Y-m-d"), "APROX");
  }


  //end DOB

 
  
	 // Append properties to QuickStatements
	$qs .= appendProp($person1->getQID(), $person1->getName('qs'));
	$qs .= appendProp($person1->getQID(), $person1->getDOB('qs'), $reference1->getQS());
	$qs .= appendProp($person1->getQID(), $person1->getDOD('qs'), $reference1->getQS());

	// Custom QuickStatement
	$customQS = isset($_POST['qs']) ? trim(strip_tags($_POST['qs'])) : "";
	$qs .= appendProp($person1->getQID(), $customQS, $reference1->getQS());

  // If described_by_source is checked, append reference as "described by source" statement
  global $described_by_source;
  if($described_by_source == "checked"){
    $qs .= appendProp($person1->getQID(), $reference1->getDescribedAtUrlQS());
  }
  
  
}


function appendProp($qid = null, $prop = null, $ref = null){
  $qs = '';
  if ($qid == null){
    $qid = "LAST";
  }
  $propLines = explode("\n", $prop);
  foreach ($propLines as $propLine) {
    if (trim($propLine) != ''){
      $qs .= "\n".$qid."|".$propLine.$ref;
    }
  }
  return $qs;

}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    handle_form_submission();
}
?>