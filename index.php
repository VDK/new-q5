<?php
include_once 'person.php';
include_once 'reference.php';
include_once 'region.php';
$error = '';
$quickStatement = false;
$region = new Region('Q81068910'); //19-20 coronavirus pandemic

if(isset($_COOKIE['selected_region'])){
    $region->setQID($_COOKIE['selected_region']);
}
if (isset($_POST['inputtext'])){
  $region->setQID($_POST['selected_region']);
  setcookie("selected_region", $region->getQID());
  $person1 = new Person();
  $text = " ".strip_tags($_POST['inputtext']); //regex doesn't like empty string.
  //set Description
  $person1->setDescription($_POST['description']);
  //set Date of Death
  $date = new DateTime();
  for ( $days = 7;  $days--;) {
    $dayOfWeek = $date->modify( '+1 days' )->format( 'l' );
    if (strripos($text, $dayOfWeek) != false){
      $person1->setDOD ('last '.$dayOfWeek);
    }
  }
  for ($months=0; $months < 11; $months++) { 
     $month = $date->modify( '+1 months' )->format( 'F' );
     if (preg_match('/('.$month.' ?([123]?\d))/i', $text, $matches)){
        $text = str_replace($matches[1], "", $text);
        $person1->setDOD($matches[1]);
     }
     elseif (preg_match('/((\b[123]?\d) ?'.$month.')/i', $text, $matches)){
        $text = str_replace($matches[1], "", $text);
        $person1->setDOD($matches[1]);
     }
     elseif (strripos($text, $month) != false){
        $person1->setDOD($month, 10);
     }
  }
  //end DOD
  //set Age
  preg_match_all("/\d+/",$text, $matches);
  if (count($matches[0]) >=1){
    rsort($matches[0]);
    $person1->setAge($matches[0][0]);
    $text = str_replace($matches[0][0], "", $text);
  }
  //end Age
  //set Name
  preg_match_all("/[\p{Lu}][\p{L}'’\-]*[\p{L}](( ([\p{Lu}][\p{L}'’\-]*[\p{L}]))*)( (([\p{Lu}]|Ph|Ch|Th)\.?)+)?(( ([\p{Lu}][\p{L}'’\-]*[\p{L}]))*) ((van|de[rsn]?|van de[srn]?|el|(in)? ['’]t|tot|te[rn]?|op|tot|uij?t|bij|aan|voor|von|Mac|Ó) )?([\p{Lu}][\p{L}'’\-]*[\p{L}])+/u", $text, $matches);
  if (count($matches[0]) >=1){
    rsort($matches[0]);
    $person1->setName($matches[0][0]);
    $text = str_replace($matches[0][0], "", $text);
  }
  //end Name
  $reference1 = new Reference($_POST['reference']);
  
	
	$quickStatement = "CREATE
LAST|Len|\"".$person1->getName()."\"
LAST|Lde|\"".$person1->getName()."\"
LAST|Lfr|\"".$person1->getName()."\"
LAST|Lnl|\"".$person1->getName()."\"
LAST|Den|\"".$person1->getDescription()."\""
."\nLAST|P31|Q5"
.concatWithRef("\n".$person1->getDOD('qs').$person1->getAge('qs'), $reference1->getQS())
.concatWithRef("\n".$person1->getDOB('qs'), $reference1->getQS())
."\n".$region->getNationality('qs')
.concatWithRef("\nLAST|P793|".$region->getQID(), $reference1->getQS()) 
.concatWithRef("\nLAST|P1196|Q3739104", $reference1->getQS()) //manner of death = natural
.concatWithRef("\nLAST|P509|Q84263196", $reference1->getQS()) //cause  of death = COVID-19
.concatWithRef("\nLAST|P1050|Q84263196|P1534|Q4|P582|+".date('Y-m-d', $person1->getDOD()).'T00:00:00Z/'.$person1->getDODAccuracy(), $reference1->getQS())//medical condition = COVID-19
."\n".$person1->getName('qs');

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
  <title>Covid-obid</title>
  <meta name="description" content="">
  <meta name="author" content="1Veertje">

  <meta http-equiv="Content-type" content="text/html; charset=utf-8" />

  <!-- Mobile Specific Metas
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- FONT
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <link href="//fonts.googleapis.com/css?family=Raleway:400,300,600" rel="stylesheet" type="text/css">

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
  <div class="container" style="margin-top: 10">
    <div class="row">
        <form class="form-wrapper"  method="POST"  target='_self' id="form"	>
			<div style='color:red; clear:both;'><?php echo $error; ?></div>
		   <div> 
        <div class="flex">
          <input type="text" id="region_label" name='region_label' value="<?php echo $region->getLabel();?>" readonly>
          <button type="button" id="up_button" style="<?php echo ($region->getParentQID() == null) ? "display:none": "";?>">up</button>
        </div>
        <input type="hidden" id="selected_region" name="selected_region" value="<?php echo $region->getQID();?>">
        <input type="hidden" id="parent" name="parent" value="<?php echo $region->getParentQID();?>">
        <label for="region">Choose a region:</label>
          <select id="region">
            <option/>
           <?php
           foreach ($region->getParts() as $qid => $label) {
             echo "<option value='".$qid."'>".$label."</option>\n";
           }
           ?>
         </select>
        <p>
          Personal details:
        <input type="text" id="inputtext" placeholder="Name, age, dod" name='inputtext' >
        <input type="text" id="description" placeholder="Description" name='description' >
        Reference:
        <input type="text" id="reference" placeholder="reference URL" name='reference' >
          </select>
        </p>
          <span style="clear:both;"/>
		    <input type="submit" class='button' value="go" id="submit">
		</div> 	
		</form>
	</div>

<?php
if ($quickStatement ){?>
   <div class="form-wrapper">
<textarea  style="height: 600px;" onClick='this.setSelectionRange(0, this.value.length)'>
<?php echo($quickStatement);	?>
</textarea>";
	</div>
<?php } ?>

</div>
<!-- End Document
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->

	
</body>

</html>