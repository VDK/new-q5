<?php
include_once 'person.php';
include_once 'reference.php';
include_once 'region.php';
$error = '';
$qs = false;

if (isset($_POST['fullname'])){
 
  $reference1 = new Reference(
    $_POST['ref_url'], 
    $_POST['ref_lang'],
    $_POST['ref_authors'], 
    $_POST['ref_title'], 
    $_POST['ref_pubdate']);
  $pubDate = strtotime("today");
  if ($reference1->getPubDate() != null){
    $pubDate = $reference1->getPubDate();
  }

  //handle person
  $person1 = new Person();
  $person1->setQID($_POST['person_QID']);
  $person1->setName($_POST['fullname']);
  $person1->setAge($_POST['age']);
  $person1->setDOB($_POST['dob']);
  $person1->setDescription($_POST['description']);
  
  //set Date of Death
  $dod = " ".trim(strip_tags($_POST['dod']));
  if ($dod != " "){
    $today = new DateTime();
    // makes in possible to input "last friday -1 weeks"
    if (!strripos("last ", $dod)){ 
      for ( $days = 7;  $days--;) {
        $dayOfWeek = $today->modify( '+1 days' )->format( 'l' );
        if (strripos($dod, $dayOfWeek) != false){
          if ($reference1->getPubDate() != null 
            && $dayOfWeek == date('l',$reference1->getPubDate())){
            $person1->setDOD(date("Y-m-d",$reference1->getPubDate()));
          }
          else{
            $person1->setDOD ('last '.$dayOfWeek." ".date("Y-m-d", $pubDate));
          }
        }
      }
    }
    if(preg_match('/\d/', $dod) && strtotime($dod) != false){
      $person1->setDOD($dod);
    }
    else{
      for ($months=0; $months < 11; $months++) { 
         $month = $today->modify( '-1 months' )->format( 'F' );
         if (strripos($dod, $month) != false){
            $person1->setDOD($month, 10);
         }
      }
    }
  }
  //end DOD
  $customQS = "";
  if (isset($_POST['qs'])){
    $customQS = trim(strip_tags($_POST['qs']));
    if(!preg_match('/[\w\W]+/', $customQS)){
      $customQS = null;
    }
  }
  
	if (!$person1->getQID()){
    $qs .= "CREATE
LAST|Len|\"".$person1->getName()."\"
LAST|Lde|\"".$person1->getName()."\"
LAST|Lfr|\"".$person1->getName()."\"
LAST|Lnl|\"".$person1->getName()."\"
LAST|Den|\"".$person1->getDescription()."\"
LAST|P31|Q5
";
  }
  $qs .= "\n".$person1->getName('qs')
  
  .concatWithRef("\n".$person1->getDOB('qs'), $reference1->getQS())
  .concatWithRef("\n".$person1->getDOD('qs').$person1->getAge('qs'), $reference1->getQS())
  .concatWithRef("\n".$customQS, $reference1->getQS())
  ."\n".$reference1->getDescribedAtUrlQS();
  
  

  if ($person1->getQID()){
    $qs = preg_replace('/^LAST/m', $person1->getQID(), $qs);
  }

}

function concatWithRef($qs, $reference){
  if (trim($qs) != ""){
    return $qs.$reference;
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

  <!-- Basic Page Needs
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <meta charset="utf-8">
  <title>New Q5</title>
  <meta name="description" content="">
  <meta name="author" content="1Veertje">

  <meta http-equiv="Content-type" content="text/html; charset=utf-8" />

  <!-- Mobile Specific Metas
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- FONT
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <!-- <link href="//fonts.googleapis.com/css?family=Raleway:400,300,600" rel="stylesheet" type="text/css"> -->

  <!-- CSS
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <link rel="stylesheet" href="css/normalize.css">
  <link rel="stylesheet" href="css/skeleton.css">
  <link rel="stylesheet" href="css/style.css">

 <!--  Favicon
  ––––––––––––––––––––––––––––––––––––––––––––––––––
  <link rel="icon" type="image/png" href="images/favicon.png"> -->
 
 <!-- Javascripts
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
<script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<script type="text/javascript" src="script.js"></script>
</head>
<body>

  <!-- Primary Page Layout
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <div class="container">
    <div class="row">
        <form class="form-wrapper"  method="POST"  target='_self' id="form"	>
			<div class="error"><?php echo $error; ?></div>
		  
        Personal details:
        <div>
        <div class="flex">
        <div style="width: 100%"><label for="fullname">name</label>
        <input type="text" id="fullname"  name='fullname' ></div>

        <div><label for="age">age</label>
        <input type="text" id="age"  name='age' style="width:100px;"></div>
      </div>
       <label for="dob">date of birth</label>
        <input type="text" id="dob"  name='dob'  >
        <label for="dod">date of death</label>
        <input type="text" id="dod"  name='dod' >
        <label for="description">short description</label>
        <input type="text" id="description" name='description' >
        <label for="qs">custom QuickStatement</label>
        <input type="text" id="qs" name='qs' >
        </div>
        <div id='possible_match' >
          <input type="hidden" name="person_QID" id="person_QID">
          <ul id='responses' ></ul>
        </div>

        Reference:
          <label for="ref_url">URL</label>
          <input type="text" id="ref_url" placeholder="https://" name='ref_url' >

          <div id="ref_params" >
            <label for="ref_title">title</label>
            <div class="flex">
              <input type="text" id="ref_lang" placeholder="lang" name="ref_lang" style="width:50px" maxlength="3" value="en">
              <input type="text" id="ref_title" name="ref_title" >
            </div>
            <label for="ref_pubdate">publcation date</label>
            <input type="date" id="ref_pubdate"  name="ref_pubdate" >
            <label for="ref_authors">author</label>
            <input type="text" id="ref_authors"  name="ref_authors" >
          </div>
          <span style="clear:both;"/>
		    <input type="submit" class='button' value="go" id="submit">
		</div> 	
		</form>

<?php
if ($qs ){?>
   <div class="form-wrapper">
<textarea id='quickstatement' style="height: 150px;" >
<?php echo($qs);	?>
</textarea>
<a onclick="sendQS()" style="cursor: pointer;">Import QuickStatement</a>
	</div>
<?php } ?>

</div>
<!-- End Document
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->

	
</body>

</html>