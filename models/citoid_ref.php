<?php
include_once 'reference.php';

class citoidRef extends reference
{
    // Documented RESTBase endpoint (returns an array of citation objects)
    private const CITOID = 'https://en.wikipedia.org/api/rest_v1/data/citation/mediawiki/';

    public function __construct(string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        // Optional: fast 404 guard (HEAD). If cURL missing, don’t block.
        if (!$this->urlLooksReachable($url)) {
            return;
        }

        self::setURL($url);

        $api = self::CITOID . urlencode($url);
        $resp = $this->httpGet($api);
        if ($resp === false) return;

        $json = json_decode($resp, true);
        if (!is_array($json) || empty($json)) return;

        // RESTBase returns an array; take the first item
        $item = $json[0];

        // Language / Title
        if (!empty($item['language'])) self::setLanguage($item['language']);
        if (!empty($item['title']))    self::setTitle($item['title']);

        // Authors (support both 'creators' and 'author' shapes)
        $authors = [];
        $arr = [];
        if (!empty($item['creators']) && is_array($item['creators'])) $arr = $item['creators'];
        elseif (!empty($item['author']) && is_array($item['author'])) $arr = $item['author'];

        foreach ($arr as $a) {
            $first = $a['firstName'] ?? ($a['given'] ?? '');
            $last  = $a['lastName']  ?? ($a['family'] ?? '');
            $name  = trim($first . ' ' . $last);
            if (!$name && !empty($a['name'])) $name = trim($a['name']);
            if ($name && !strtotime($name)) $authors[] = $name;
        }
        self::setAuthors($authors);

        // Date (keep raw; controller will normalize)
        if (isset($item['date'])) {
            self::setPubDate($item['date']);
        } elseif (isset($item['datePublished'])) {
            self::setPubDate($item['datePublished']);
        }
    }

    /** Quick reachability check via HEAD; returns true if unknown (no cURL) */
    private function urlLooksReachable(string $url): bool
    {
        if (!function_exists('curl_init')) return true;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => ['User-Agent: New-Q5/2.0 (+https://veradekok.nl/contact)'],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 404) return false;
        return true; // don’t over-block on other statuses
    }

    /** Robust GET via cURL with fopen fallback */
    private function httpGet(string $url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'User-Agent: New-Q5/2.0 (+https://veradekok.nl/contact)',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($resp !== false && $code >= 200 && $code < 400) ? $resp : false;
        }

        // Fallback if cURL not available
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 15,
                'header'  => "Accept: application/json\r\nUser-Agent: New-Q5/2.0 (+https://veradekok.nl/contact)\r\n",
            ],
        ]);
        $resp = @file_get_contents($url, false, $context);
        return $resp !== false ? $resp : false;
    }
}
