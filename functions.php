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




function getEntityClaims($qid) {
    $entityUrl = "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
    $entityJson = @file_get_contents($entityUrl);
    if (!$entityJson) return [];

    $entityData = json_decode($entityJson, true);
    $entry = $entityData['entities'][$qid] ?? null;
    if (!$entry || !isset($entry['claims'])) return [];

    $claims = [];
    foreach ($props as $pid) {
        $claims[$pid] = [];
        foreach ($entry['claims'][$pid] ?? [] as $claim) {
            $value = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if ($value) {
                $claims[$pid][] = $value;
            }
        }
    }

    return $claims;
}


?>