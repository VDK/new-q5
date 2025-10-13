<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
include_once 'controller.php';
require_once 'notice_controller.php';
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
<script>
  window.PREFILL_PROPS = <?php echo json_encode($prefill_props ?: []); ?>;
</script>

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
<div id="top-icons">
  <a href="#" id="reopen" aria-label="reopen" <?php if (!get_notice_closed()) echo 'style="display: none;"'; ?>>?</a>
  <span id="share_slot_top"></span>
</div>

<div id="notice" <?php if (get_notice_closed()) echo 'style="display: none;"'; ?>>
  <a href="#" id="close" aria-label="close">x</a>
  <p>This is a small form for adding or updating a person's data in <a href="https://www.wikidata.org/wiki/Wikidata:Main_Page" target="_blank">Wikidata</a>.</p>
  <p>For a full list of features and disclaimers, please consult the <a href="https://github.com/VDK/new-q5/blob/main/README.md">README</a>.</p>
  <p>Please make sure new items conform to the <a href="https://www.wikidata.org/wiki/Wikidata:Notability" target="_blank">notability standards</a>, especially those for <a href="https://www.wikidata.org/wiki/Wikidata:Living_people" target="_blank">living people</a>.</p>
</div>

<a href="#" id="reopen" aria-label="reopen" <?php if (!get_notice_closed()) echo 'style="display: none;"'; ?>>?</a>



        <p>Personal details:</p>
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
   
        <!-- Custom statements (repeatable) -->
<div class="pv-list" id="pv_list" data-imdb-pid="P345">
  <div class="pv-row">
    <button type="button" class="pv-remove" aria-label="Remove statement">✕</button>
    <!-- Property -->
    <div class="pv-field pv-prop">
      <label for="pv_prop">Property (P…)</label>
      <input type="text" id="pv_prop" autocomplete="off" placeholder="">
      <ul class="pv-suggest" id="pv_prop_results" role="listbox"></ul>
      <div class="pv-meta" id="pv_prop_meta" aria-live="polite"></div>
    </div>

    <!-- Value: Q-item -->
    <div class="pv-field pv-value pv-value-item" id="pv_value_item">
      <label for="pv_value_q">Value (Q…)</label>
      <input type="text" id="pv_value_q" autocomplete="off" placeholder="">
      <ul class="pv-suggest" id="pv_value_results" role="listbox"></ul>
      <div class="pv-meta" id="pv_value_meta" aria-live="polite"></div>
    </div>

    <!-- Value: External identifier -->
    <div class="pv-field pv-value pv-value-external" id="pv_value_ext" hidden>
      <label for="pv_value_ext_input">External identifier value</label>
      <input type="text" id="pv_value_ext_input" placeholder="">
    </div>

    <!-- Hidden, authoritative fields submitted to PHP -->
    <input type="hidden" class="prop_pid  prop_pid_0"  name="pv[0][p]">
    <input type="hidden" class="value_qid value_qid_0" name="pv[0][v]">
    <input type="hidden" class="ext_val  ext_val_0"  name="pv[0][ext]">

    <!-- Options -->
    <div class="pv-field pv-options">
 
      <input type="checkbox" id="pv_ref_0" name="pv[0][ref]" checked>
           <label for="pv_ref_0">Add ref</label>
    </div>
  </div>
</div>



<div class="pv-toolbar">
  <button type="button" class="button" id="pv_add_row" alt="add statement">Add</button>
</div>

<div style="clear:both;"/>


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
            <label for="ref_pubdate">publication date</label>
            <input type="date" id="ref_pubdate"  name="ref_pubdate" />
            <label for="ref_authors">author</label>
            <input type="text" id="ref_authors"  name="ref_authors" />


           <div id="description"> <input type="checkbox" id="described_by_source" name="described_by_source" <?= described_by_source() ?>>
            <label id="described_label" for="described_by_source">Include reference as <a href="https://www.wikidata.org/wiki/Property:P973" target="_blank">described by url (P973)</a> statement</label></div>

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