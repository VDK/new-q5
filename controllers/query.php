<?php


require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../models/imdb.php';
require_once __DIR__ . '/../models/person.php';


$labelCache = []; // shared cache for all getLabel() calls
$langPref   = ['en','mul','nl'];







if (isset($_GET['srsearch'])) {
    $term = trim($_GET['srsearch'] ?? '');
    $extp = [];
    if (!empty($_GET['extp'])) {
        // extp can be "P345,P214" or repeated extp[]=P345&extp[]=P214
        $raw = is_array($_GET['extp']) ? $_GET['extp'] : explode(',', $_GET['extp']);
        foreach ($raw as $p) {
            $p = strtoupper(trim($p));
            if (preg_match('/^P\d+$/', $p)) $extp[] = $p;
        }
        $extp = array_values(array_unique($extp));
    }

    $qids = Person::searchHumanQids($term, 10);
    $data = Person::fetchMetadata($qids, $extp);   // ← pass P’s
    send_json($data);
    exit;
}




// ---- P+V picker endpoints ----

// ---- New-Q5: P+V picker endpoints ----


//make it possible to load P's labels

if (!empty($_GET['ids'])) {
    $ids  = array_filter(array_map('trim', explode('|', $_GET['ids'])));
    $lang = $_GET['lang'] ?? 'en';

    $out = [];
    foreach ($ids as $id) {
        if (!preg_match('/^P\d+$/', $id)) continue;
        $label = getLabel($id, $labelCache, $lang);
        $out[] = [
            'id'       => $id,
            'label'    => $label,
            'datatype' => null // optionally fill later if you want
        ];
    }

    send_json($out);
    exit;
}



// Property search (reuse fetch_prop_meta_from_ids)
if (isset($_GET['pv']) && $_GET['pv'] === 'propsearch') {
    $qRaw = (string)($_GET['q'] ?? '');
    $q    = trim($qRaw);
    $lang = preg_replace('/[^a-z\-]/i', '', (string)($_GET['lang'] ?? 'en')) ?: 'en';

    if ($q === '' || mb_strlen($q) < 2) {
        send_json([]);
    }

    // 1) Search properties
    $search = wd_api_query([
        'action'   => 'wbsearchentities',
        'format'   => 'json',
        'type'     => 'property',
        'search'   => $q,
        'language' => $lang,
        'uselang'  => $lang,
        'limit'    => 20,
    ]);
    $hits = is_array($search['search'] ?? null) ? $search['search'] : [];
    if (!$hits) {
        send_json([]);
    }

    // 2) Extract PIDs in order (deduped)
    $seen = [];
    $ids  = [];
    foreach ($hits as $h) {
        $pid = strtoupper(trim((string)($h['id'] ?? '')));
        if ($pid === '' || !preg_match('/^P\d+$/', $pid)) continue;
        if (isset($seen[$pid])) continue;
        $seen[$pid] = true;
        $ids[] = $pid;
    }
    if (!$ids) {
        send_json([]);
    }

    // 3) Reuse your helper for datatype gating + labels
    $meta = fetch_prop_meta_from_ids($ids, $lang);
    if (!$meta) {
        send_json([]);
    }

    // 4) Merge search descriptions back in, keep meta order
    $descById = [];
    foreach ($hits as $h) {
        $pid = strtoupper(trim((string)($h['id'] ?? '')));
        if ($pid) $descById[$pid] = (string)($h['description'] ?? '');
    }

    $out = [];
    foreach ($meta as $m) { // $m = ['id','label','datatype']
        $pid = $m['id'];
        $out[] = [
            'id'          => $pid,
            'label'       => $m['label'],
            'description' => $descById[$pid] ?? '',
            'datatype'    => $m['datatype'], // already filtered to wikibase-item/external-id
        ];
    }

    send_json($out);
}


// Item (Q) search
if (isset($_GET['pv']) && $_GET['pv'] === 'itemsearch') {
    $q    = trim($_GET['q'] ?? '');
    $lang = preg_replace('/[^a-z\-]/i', '', $_GET['lang'] ?? 'en');

    if ($q === '') send_json([]);

    $s = wd_api_query([
        'action'   => 'wbsearchentities',
        'format'   => 'json',
        'type'     => 'item',
        'search'   => $q,
        'language' => $lang,
        'uselang'  => $lang,
        'limit'    => 20
    ]);

    $out = [];
    foreach (($s['search'] ?? []) as $h) {
        $out[] = [
            'id'          => $h['id'] ?? '',
            'label'       => $h['label'] ?? '',
            'description' => $h['description'] ?? ''
        ];
    }
    send_json($out);
}



if (isset($_GET['endpoint']) && $_GET['endpoint'] === 'imdb') {
    try {
        $input = $_GET['id'] ?? $_GET['url'] ?? $_GET['q'] ?? '';
        $imdb = new IMDB($input);

        if (!$imdb->fetch()) {
            send_json(['ok' => false, 'error' => 'IMDb API request failed'], 502);
        }

        $data = $imdb->normalize();
        send_json(['ok' => true] + $data);

    } catch (Exception $e) {
        send_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}


?>