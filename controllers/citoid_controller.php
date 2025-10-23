<?php
// citoid_controller.php — returns {url,title,language,authors,pubdate}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../models/citoid_ref.php';

function out($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$url = $_GET['url'] ?? '';
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    out(['error' => 'Missing or invalid url parameter'], 400);
}

$ref = new citoidRef($url);

// pull from your reference model (keep method names flexible)
$get = fn($m) => method_exists($ref, $m) ? $ref->$m() : null;

$authors = $get('getAuthors');
if (is_array($authors)) {
    $authors = implode('|', array_filter(array_map('trim', $authors)));
} elseif (!is_string($authors)) {
    $authors = '';
}

$out = [
    'url'      => $get('getURL')      ?: $url,
    'title'    => $get('getTitle')    ?: '',
    'language' => $get('getLanguage') ?: '',
    'authors'  => $authors,
    'pubdate'  => $get('getPubDateString')  ?: '',
];

if ($out['title'] === '' && $out['authors'] === '' && $out['pubdate'] === '') {
    out(['error' => 'No metadata found for URL'], 502);
}

out($out);
?>