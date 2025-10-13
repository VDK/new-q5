<?php
function sparqlQuery(string $sparqlQuery): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                "Accept: application/sparql-results+json",
                "Accept-Language: en",
                "User-Agent: WikidataQuickStatementsBot/1.0 (https://veradekok.nl/contact/)"
            ]) . "\r\n"
        ]
    ];

    $context = stream_context_create($opts);
    $url = 'https://query.wikidata.org/sparql?query=' . urlencode($sparqlQuery);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return [];
    }

    return json_decode($response, true);
}



function searchWikidataEntity(string $label, array $allowedInstanceOf = []): array {
    $params = [
        'action'          => 'wbsearchentities',
        'format'          => 'json',
        'language'        => 'en',
        'search'          => $label,
        'type'            => 'item',
        'limit'           => 10,
        'strictlanguage'  => 1,
        // 'uselang'      => 'en', // optional
    ];
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query($params);

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
                'timeout' => 8,
            ]
        ]);
    }

    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return [];

    $data = json_decode($json, true);
    $hits = [];
    foreach ($data['search'] ?? [] as $hit) {
        $hits[] = [
            'id'    => $hit['id'] ?? null,
            'label' => $hit['label'] ?? '',
        ];
    }

    // Optional: filter by instance-of using one batched SPARQL query.
    if ($allowedInstanceOf) {
        $ids = array_column($hits, 'id');
        if ($ids) {
            $values = 'wd:' . implode(' wd:', array_map('trim', $ids));
            $allow  = 'wd:' . implode(' wd:', array_map('trim', $allowedInstanceOf));
            $sparql = <<<SPARQL
SELECT ?item WHERE {
  VALUES ?item { $values }
  ?item wdt:P31 ?inst .
  VALUES ?inst { $allow }
}
SPARQL;
            $ok = [];
            foreach ((sparqlQuery($sparql)['results']['bindings'] ?? []) as $row) {
                $ok[] = basename($row['item']['value']);
            }
            $hits = array_values(array_filter($hits, fn($h) => in_array($h['id'], $ok, true)));
        }
    }

    return $hits;
}



/* very expensive, avoid use */
function getEntityClaims(string $qid, array $props = []): array {
    $entityUrl = "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
    $entityJson = @file_get_contents($entityUrl);
    if (!$entityJson) return [];

    $entityData = json_decode($entityJson, true);
    $entry = $entityData['entities'][$qid] ?? null;
    if (!$entry || !isset($entry['claims'])) return [];

    $claims = [];

    // if no $props specified, include all property keys
    $props = $props ?: array_keys($entry['claims']);

    foreach ($props as $pid) {
        $claims[$pid] = [];
        foreach ($entry['claims'][$pid] ?? [] as $claim) {
            $value = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if ($value) $claims[$pid][] = $value;
        }
    }
    return $claims;
}

// Stable unique (preserve first occurrence)
function unique_stable(array $a): array {
    $seen = [];
    $out  = [];
    foreach ($a as $v) {
        if (!isset($seen[$v])) { $seen[$v] = true; $out[] = $v; }
    }
    return $out;
}

function is_entity_id($s): bool {
    return is_string($s) && preg_match('/^[QP]\d+$/', $s) === 1;
}


function wd_api_query(array $params) {
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query($params);

    static $context = null;
    if ($context === null) {
        $opts = [
            "http" => [
                "header" => "User-Agent: New-Q5/2.0 (https://veradekok.nl/contact)\r\n"
            ]
        ];
        $context = stream_context_create($opts);
    }

    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception("Wikidata API request failed: $url");
    }

    return json_decode($response, true);
}

function send_json($payload, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}


/**
 * Return a human-readable label for a Q/P id.
 * - $lang can be string ('nl') or array(['nl','de']).
 * - Always falls back to 'mul' (then 'en') if not present.
 * - $cache is optional; if null, it will be created.
 */

function getLabel(string $pid, ?array &$cache = null, $lang = 'en'): string {
    if (!is_entity_id($pid)) return $pid;

    if ($cache === null) $cache = [];

    if (isset($cache[$pid])) {
        return $cache[$pid];
    }

    // Normalize language preference and ensure 'mul' fallback
    $langs = is_array($lang) ? $lang : [$lang];
    // append 'mul' then 'en' if missing
    if (!in_array('mul', $langs, true)) $langs[] = 'mul';
    if (!in_array('en',  $langs, true)) $langs[] = 'en';

    // Try APCu (optional)
    $apcu_key = 'wd:label:' . implode('|', $langs) . ':' . $pid;
    if (function_exists('apcu_fetch')) {
        $hit = apcu_fetch($apcu_key, $ok);
        if ($ok && is_string($hit)) {
            return $cache[$pid] = $hit;
        }
    }

    $map = wd_label_map([$pid], $langs);
    $label = $map[$pid] ?? $pid;

    if (function_exists('apcu_store')) {
        apcu_store($apcu_key, $label, 86400);
    }
    return $cache[$pid] = $label;
}

/**
 * Fetch labels for multiple ids with ordered language preference.
 * $langs may be string or array. Always ensures 'mul' (then 'en') is included.
 */
function wd_label_map(array $ids, $langs = 'en'): array {
    $ids = array_values(array_unique(array_filter($ids, 'is_entity_id')));

    if (!$ids) return [];

    $langs = is_array($langs) ? $langs : [$langs];
    if (!in_array('mul', $langs, true)) $langs[] = 'mul';
    if (!in_array('en',  $langs, true)) $langs[] = 'en';

    // Build API request
    $params = [
        'action'    => 'wbgetentities',
        'format'    => 'json',
        'ids'       => implode('|', $ids),
        'props'     => 'labels',
        // Request *all* languages we might accept, so we can choose our own fallback order
        'languages' => implode('|', $langs),
        // languagefallback=1 uses WD's internal chain; we still prefer our explicit order incl. 'mul'
        'languagefallback' => 1,
    ];

    $data = wd_api_query($params);

    $out = [];
    if (!empty($data['entities']) && is_array($data['entities'])) {
        foreach ($ids as $id) {
            $label = null;
            if (isset($data['entities'][$id]['labels'])) {
                $lbls = $data['entities'][$id]['labels'];
                // Pick first available in our desired order
                foreach ($langs as $L) {
                    if (isset($lbls[$L]['value']) && $lbls[$L]['value'] !== '') {
                        $label = $lbls[$L]['value'];
                        break;
                    }
                }
            }
            $out[$id] = $label ?? $id;
        }
    }

    return $out;
}






// In functions.php (or wherever your helpers live)
function fetch_prop_meta_from_ids(array $raw, string $lang='en'): array {
    // 1) keep only tokens that look like P-ids, preserve order, dedupe
    $seen = [];
    $pids = [];
    foreach ($raw as $t) {
        $t = trim($t);
        if (!preg_match('/^P\d+$/i', $t)) continue;
        $t = strtoupper($t);
        if (isset($seen[$t])) continue;
        $seen[$t] = true;
        $pids[] = $t;
    }
    if (!$pids) return [];

    // 2) fetch entities once
    $res = wd_api_query([
        'action'    => 'wbgetentities',
        'ids'       => implode('|', $pids),
        'props'     => 'labels|datatype',
        'languages' => $lang,
        'format'    => 'json'
    ]);
    $entities = $res['entities'] ?? [];

    // 3) keep only wikibase-item or external-id
    $allowed = ['wikibase-item', 'external-id'];
    $out = [];
    foreach ($pids as $pid) {
        $e  = $entities[$pid] ?? null;
        if (!$e) continue;
        $dt = $e['datatype'] ?? '';
        if (!in_array($dt, $allowed, true)) continue;

        $label = $e['labels'][$lang]['value'] ?? $pid;
        $out[] = [
            'id'       => $pid,
            'label'    => $label,
            'datatype' => $dt,
        ];
    }
    return $out;
}



?>