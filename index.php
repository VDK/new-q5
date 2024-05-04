<?php
include_once 'controller.php';

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
        <form class="form-wrapper"  method="POST"  target='_self' id="form" >
      <div class="error"><?php echo $error; ?></div>
        <noscript>
          <div class="error">
            This site requires JavaScript.
          </div>
        </noscript>
      
        Personal details:
        <div>
        <div class="flex">
        <div style="width: 100%"><label for="fullname">name</label>
        <input type="text" id="fullname"  name='fullname'/ ></div>

        <div><label for="age">age</label>
        <input type="text" id="age"  name='age' style="width:100px;"/></div>
      </div>
       <label for="dob">date of birth</label>
        <input type="text" id="dob"  name='dob' />
        <label for="dod">date of death</label>
        <input type="text" id="dod"  name='dod'/ >
        <label for="description">short description</label>
        <input type="text" id="description" name='description' />
        <label for="qs">custom QuickStatement</label>
        <input type="text" id="qs" name='qs' />
        </div>
        <div id='possible_match' >
          <input type="hidden" name="person_QID" id="person_QID"/>
          <ul id='responses' ></ul>
        </div>

        Reference:
          <label for="ref_url">URL</label>
          <input type="text" id="ref_url" placeholder="https://" name='ref_url' />

          <div id="ref_params" >
            <label for="ref_title">title</label>
            <div class="flex">
              <input type="text" id="ref_lang" placeholder="lang" name="ref_lang" style="width:50px" maxlength="3" value="en" />
              <input type="text" id="ref_title" name="ref_title" />
            </div>
            <label for="ref_pubdate">publcation date</label>
            <input type="date" id="ref_pubdate"  name="ref_pubdate" />
            <label for="ref_authors">author</label>
            <input type="text" id="ref_authors"  name="ref_authors" />
            <input id="described" type="checkbox" name="described_by_source" id="described_by_source" <?php echo $described_by_source; ?> />
            <label id="described_label" for="described_by_source">Include reference as "described by source" statement</label>
          </div>
          <span style="clear:both;"/>
        <input type="submit" class='button' value="go" id="submit" />
    </form>
    </div>  
<?php
if ($qs ){?>
  <div class="row">
   <form class="form-wrapper">
<textarea id='quickstatement' style="height: 150px;" >
<?php echo($qs);  ?>
</textarea>
<a onclick="sendQS()" style="cursor: pointer;">Import QuickStatement</a>
  </form>
    </div>  
<?php } ?>
    </div>  

 <footer id="footer"><a href="https://github.com/VDK/New-Q5" target="_blank">Code on GitHub</a> <br/>available under MIT license</footer>
</div>
<!-- End Document
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->

  
</body>

</html>