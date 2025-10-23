<?php

include_once './models/person.php';
include_once './models/reference.php';
include_once './lib/qs_helpers.php';
include_once './lib/functions.php';
$error = '';
$qs = false;



function described_by_source() {
    // If form not submitted and cookie set
    if (isset($_COOKIE['described_by_source']) && empty($_POST)) {
        return ($_COOKIE['described_by_source'] === 'checked') ? 'checked' : '';
    }

    // If the user has checked the box in this POST request
    if (isset($_POST['described_by_source'])) {
        return 'checked';
    }

    // Otherwise, leave unchecked
    return '';
}


$described_by_source  = described_by_source();
setcookie("described_by_source", $described_by_source , time() +  (86400 * 30 * 100));

/** Resolve GET ?property=P31|P569|... -> [{id,label,datatype}] */
function fetch_prop_meta(array $pids, string $lang = 'en'): array {
    // keep only P… and unique in order
    $pids = array_values(array_unique(array_filter($pids, fn($s)=>preg_match('/^P\d+$/i',$s))));
    if (!$pids) return [];

    // wbgetentities
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
        'action'    => 'wbgetentities',
        'ids'       => implode('|',$pids),
        'props'     => 'labels|datatype',
        'languages' => $lang,
        'format'    => 'json'
    ]);

    static $ctx = null;
    if ($ctx === null) {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", [
                    'Accept: application/json',
                    'Accept-Language: en',
                    'User-Agent: New-Q5/2.0 (https://veradekok.nl/contact)'
                ]) . "\r\n",
                'timeout' => 10,
            ]
        ]);
    }

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return array_map(fn($id)=>['id'=>$id,'label'=>$id,'datatype'=>null], $pids);

    $data = json_decode($json, true);
    $entities = $data['entities'] ?? [];

    $out = [];
    foreach ($pids as $id) {
        $e = $entities[$id] ?? [];
        $label = $e['labels'][$lang]['value'] ?? $id;
        $out[] = [
            'id'       => $id,
            'label'    => $label,
            'datatype' => $e['datatype'] ?? null,
        ];
    }
    return $out;
}
// Expose prefill props for this request (URL stickiness source of truth)
$prefill_props = [];
if (!empty($_GET['property'])) {
    $raw = explode('|', $_GET['property']);
    $prefill_props = fetch_prop_meta_from_ids($raw, $_GET['lang'] ?? 'en');
}


// Function to handle form submission
function handle_form_submission() {
  // Use either local or global, not both. If you need it only here:
  $qs = '';

  // If you truly need the global, do this instead:
  // global $qs; $qs = '';

  $reference1 = new Reference(
      $_POST['ref_url'] ?? null,
      $_POST['ref_lang'] ?? null,
      $_POST['ref_authors'] ?? null,
      $_POST['ref_title'] ?? null,
      $_POST['ref_pubdate'] ?? null
  );

  $loopDate = new DateTime();
  $today    = new DateTime();

  $pubDate = $reference1->getPubDate();

  // Safely normalize $pubDate to a DateTime if possible
  if ($pubDate instanceof DateTimeInterface) {
      $today = clone $pubDate;
  } elseif (is_string($pubDate) && trim($pubDate) !== '') {
      try {
          $today = new DateTime($pubDate);
      } catch (Exception $e) {
          // leave $today as "now" or handle the parse failure as you prefer
      }
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
    if (strripos($dod, "last ") !== false) { 
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
  if ($person1->getDOD() === null && $person1->getAge() !== null) {
      $pub = $reference1->getPubDate(); // DateTimeImmutable|null
      if ($pub) {
          $dob = $pub->modify("-{$person1->getAge()} years")->format('Y-m-d');
          $person1->setDOB($dob, 'APROX');
      }
  }




  //end DOB

  // construct QuickStatement 
  if (!$person1->getQID()){
    $qs .= 
  "CREATE
  LAST|Lmul|\"".$person1->getName()."\"
  LAST|Len|\"".$person1->getName()."\"
  LAST|Lde|\"".$person1->getName()."\"
  LAST|Lfr|\"".$person1->getName()."\"
  LAST|Lnl|\"".$person1->getName()."\"
  LAST|Den|\"".$person1->getDescription()."\"
  LAST|P31|Q5";
  }

  // ------------------ NEW: update description on existing when requested ------------------
  $last = $person1->getQID() ?: 'LAST';
  if ($person1->getQID() && !empty($_POST['update_description'])) {
    $desc = $person1->getDescription();
    if ($desc !== '') {
      $qs .= "\n".$last."|Den|\"".$desc."\"";
    }
  }

  // ------------------ NEW: aliases_en[] -> Amul ------------------
  $aliases_en = $_POST['aliases_en'] ?? [];
  if (is_array($aliases_en) && !empty($aliases_en)) {
    // trim + de-dup + drop empties
    $aliases_en = array_values(array_unique(array_filter(array_map('trim', $aliases_en))));
    foreach ($aliases_en as $a) {
      if ($a !== '') {
        $qs .= "\n".$last."|Amul|\"".$a."\"";
      }
    }
  }

  // Append properties to QuickStatements
  $qs .= appendProp($person1->getQID(), $person1->getGender('qs'));
  $qs .= appendProp($person1->getQID(), $person1->getName('qs'));
  $qs .= appendProp($person1->getQID(), $person1->getDOB('qs'), $reference1->getQS());
  $qs .= appendProp($person1->getQID(), $person1->getDOD('qs'), $reference1->getQS());

  // NEW: add dynamic p/v rows (robust to ext-as-value or ext-as-flag)
  $pv_rows_raw = $_POST['pv'] ?? [];
  if (!empty($pv_rows_raw)) {
    $pv_rows = normalize_pv_from_post($pv_rows_raw); // your existing helper

    foreach ($pv_rows as $row) {
      $p = _qs_norm_prop($row['p'] ?? '');
      if (!$p) continue;

      $val = _qs_fmt_value($row['v'] ?? '', !empty($row['ext'])); // ext => external id formatting
      if ($val === '') continue;

      $line = $p . '|' . $val;
      $qs  .= appendProp(
        $person1->getQID(),
        $line,
        !empty($row['ref']) ? $reference1->getQS() : null
      );
    }
  }




  // If described_by_source is checked, append reference as "described by source" statement
  global $described_by_source;
  if($described_by_source == "checked"){
    $qs .= appendProp($person1->getQID(), $reference1->getDescribedAtUrlQS());
  }
  
  return $qs;
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $qs = handle_form_submission();
}
?>