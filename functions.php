<?php
function sparqlQuery(string $sparqlQuery): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                "Accept: application/sparql-results+json",
                "Accept-Language: en",
                "User-Agent: WikidataQuickStatementsBot/1.0 (https://your.domain/; your@email.com)"
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



function searchWikidataEntity($searchTerm, $p31Filters = [], $limit = 3) {
    $apiUrl = 'https://www.wikidata.org/w/api.php';

    $srsearch = '';
    if (!empty($p31Filters)) {
        $parts = array_map(function ($qid) {
            return "P31=$qid";
        }, $p31Filters);

        $srsearch .= 'haswbstatement:"' . implode('|', $parts) . '" ';
    }
    $srsearch .= '"' . $searchTerm . '"';

    $params = [
        'action'   => 'query',
        'format'   => 'json',
        'list'     => 'search',
        'srsearch' => $srsearch,
        'srlimit'  => $limit,
        'utf8'     => 'true'
    ];

    $url = $apiUrl . '?' . http_build_query($params);
    $response = @file_get_contents($url);
    if ($response === false) return [];

    $data = json_decode($response, true);
    $results = $data['query']['search'] ?? [];
    return array_map(function ($entry) {
        return [
            'id' => $entry['title'],
            'label' => $entry['title'], // placeholder label
            'description' => $entry['snippet'] ?? ''
        ];
    }, array_filter($results, function ($r) {
        return preg_match('/^Q\d+$/', $r['title'] ?? '');
    }));
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