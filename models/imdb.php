<?php

include_once __DIR__ . '/../lib/functions.php';
include_once __DIR__ . '/person.php';
class IMDB extends Person
{
    private string $id;
    private array $data = [];

    public function __construct(string $input)
    {
        $this->id = $this->extractId($input);
    }

    private function extractId(string $s): string
    {
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?imdb\.com\/name\/(nm\d{7,8})/i', $s, $m)) {
            return strtolower($m[1]);
        }
        if (preg_match('/\b(nm\d{7,8})\b/i', $s, $m)) {
            return strtolower($m[1]);
        }
        throw new InvalidArgumentException('Invalid IMDb ID or URL');
    }

    public function fetch(): bool
    {
        $url = "https://api.imdbapi.dev/names/{$this->id}";
        $opts = [
            "http" => [
                "header"  => "User-Agent: New-Q5/2.0 (https://veradekok.nl/contact)\r\nAccept: application/json\r\n",
                "timeout" => 12,
            ]
        ];
        $context = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) return false;
        $this->data = json_decode($raw, true) ?: [];
        return isset($this->data['id']);
    }

    public function normalize(): array
    {
        $map = self::professionMap();

        $displayName   = $this->data['displayName'] ?? null;
        $altNames      = array_values(array_filter($this->data['alternativeNames'] ?? []));
        $professions   = array_map('strtolower', $this->data['primaryProfessions'] ?? []);
        $birthName     = $this->data['birthName'] ?? null;
        $birthLocation = $this->data['birthLocation'] ?? null;
        $biography     = $this->data['biography'] ?? null;

        // ⬇ combineer birthName met altNames (zonder duplicaten)
        if ($birthName && !in_array($birthName, $altNames, true)) {
            $altNames[] = $birthName;
        }

        $professions = self::normalizeProfessions($professions);

        $p106_qids  = [];
        $prof_order = [];                    // remember first-seen order for labels

        foreach (($professions ?? []) as $pRaw) {
            $p = strtolower(trim((string)$pRaw));
            if ($p === '') continue;

            // keep your local canonicalization for mapping purposes (writer->screenwriter, etc.)
            $qid = $map[$p] ?? $map[$p] ?? null;
            if ($qid && !isset($p106_qids[$qid])) {
                $p106_qids[$qid] = true;     // dedupe by QID
                $prof_order[] = $qid;        // preserve order
            }
        }

        // ---- resolve labels from Wikidata (single-source-of-truth) ----
        $labelCache = null;
        $langPref   = $_GET['ref_lang'] ?? $_GET['lang'] ?? 'en';   // or your own lang logic

        $p106        = [];
        $prof_labels = [];
        $seenLabel   = [];

        foreach ($prof_order as $qid) {
            // canonical WD label (cached via getLabel → wd_label_map/APCu)
            $wdLabel = getLabel($qid, $labelCache, $langPref);

            // super-defensive fallback if WD label missing for some reason
            if ($wdLabel === $qid || $wdLabel === '') {
                // optional: last-resort to local canonicalization for readability
                // (Not strictly needed if you’re okay showing the QID)
                $wdLabel = $wdLabel ?: $qid;
            }

            $p106[] = ['qid' => $qid, 'label' => $wdLabel];

            if (!isset($seenLabel[$wdLabel])) {
                $prof_labels[] = $wdLabel;
                $seenLabel[$wdLabel] = true;
            }
        }

        $natData = self::inferNationalityFromLabel($birthLocation);
        [$desc_demonym, $desc_noun] = self::buildDescriptions($natData['demonym'], $natData['phrase'], $professions);
        $nationality_label = $natData['nationality_qid'] ? getLabel($natData['nationality_qid'], $labelCache, $langPref) : null;


        // alias_candidates = alle alternatieve namen die niet gelijk zijn aan de displayName
        $alias_candidates = array_values(array_filter(
        $altNames,
            fn($a) => strcasecmp($a, $displayName ?? '') !== 0
        ));

        $desc_bio = null;
        if (!empty($biography)) {
            // Normalize whitespace
            $bio1 = preg_replace('/\s+/u', ' ', trim($biography));

            // Try: "... is a/an <CAPTURE> who/that/[,|.|;| end]"
            if (preg_match('/\bis\s+a?n?\s+(.+?)(?:\s+(?:who|that)\b|[.,;]|$)/iu', $bio1, $m)) {
                $cand = trim($m[1]);
                // Keep it short-ish; strip trailing “and …” tails if they run long
                $cand = preg_replace('/\s+and\s+.+$/iu', '', $cand);
                // Capitalization normalization: keep as-is, but collapse interior spaces
                $cand = preg_replace('/\s+/u', ' ', $cand);
                if ($cand !== '') $desc_bio = $cand;
            }
        }




        return [
            'id'                         => $this->id,
            'displayName'                => $displayName,
            'primaryProfessions'         => $professions,
            'biography'                  => $biography,
            'birthLocation'              => $birthLocation,
            'alias_candidates'           => $alias_candidates,
            'description_suggest_en_demonym' => $desc_demonym,
            'description_suggest_en_noun'    => $desc_noun,
            'description_suggest_en_bio'      => $desc_bio,
            'p106'                       => $p106,
            'nationality_qid'           => $natData['nationality_qid'],
            'nationality_label'         => $nationality_label,
            'demonym'                   => $natData['demonym'],
            'country_label'             => $natData['country'],
            'country_qid'               => $natData['country_qid'],

        ];

    }
    
    protected static function normalizeProfessions(array $labels): array
    {
        // map shorthand to canonical forms first
        static $map = [
            'director' => 'film director',
            'writer'   => 'screenwriter',
            'producer' => 'film producer',
        ];

        $clean = [];
        foreach ($labels as $p) {
            $p = trim(mb_strtolower((string)$p));
            if ($p === '') continue;
            $clean[] = $map[$p] ?? $p;
        }

        // dedupe, restore nice casing
        $labels = array_values(array_unique($clean));

        // IMDb-specific condensation rules
        $filmRoles = ['film director', 'film producer', 'screenwriter'];
        $hasAllFilmRoles = !array_diff($filmRoles, $labels);
        if ($hasAllFilmRoles) {
            return ['filmmaker'];
        }

        return $labels;
    }



    private static function professionMap(): array
    {
        return [
            // --- acting & performance ---
            'actor'              => 'Q33999',
            'actress'            => 'Q33999',      // merged as actor
            'voice actor'        => 'Q2405480',
            'stunt performer'    => 'Q465501',

            // --- directing & producing ---
            'film director'      => 'Q2526255',    // film director
            'film producer'      => 'Q3282637',    // film producer
            'television producer'=> 'Q578109',
            'tv producer'        => 'Q578109',
            'executive producer' => 'Q3282637',    // fallback to film producer
            'filmmaker'          => 'Q1414443',

            // --- writing ---
            'writer'             => 'Q28389',      // screenwriter (default)
            'screenwriter'       => 'Q28389',
            'story writer'       => 'Q28389',
            'playwright'         => 'Q214917',
            'author'             => 'Q482980',     // prose writer

            // --- technical roles ---
            'cinematographer'    => 'Q222344',
            'editor'             => 'Q7042855',    // film editor
            'film editor'        => 'Q7042855',
            'camera operator'    => 'Q1043449',
            'production designer'=> 'Q2962070',
            'art director'       => 'Q706364',
            'costume designer'   => 'Q1323191',
            'makeup artist'      => 'Q935666',

            // --- music roles ---
            'composer'           => 'Q1415090',    // film score composer
            'music composer'     => 'Q1415090',
            'musician'           => 'Q639669',
            'singer'             => 'Q177220',
            'songwriter'         => 'Q753110',

            // --- media / presenting ---
            'television presenter'=> 'Q947873',
            'tv presenter'       => 'Q947873',
            'radio presenter'    => 'Q947873',
            'broadcaster'        => 'Q947873',

            // --- other / general ---
            'assistant director' => 'Q2526255',    // fallback to film director
        ];

    }


}
