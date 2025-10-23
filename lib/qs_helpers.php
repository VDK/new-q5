<?php


function normalize_pv_from_post($pv_raw): array {
  if (!is_array($pv_raw)) return [];
  $is_booly = static fn($x) => in_array((string)$x, ['0','1','on','off'], true);

  $mk = static function($p,$v,$ext,$ref) use ($is_booly) {
    $p = trim((string)$p);
    $v = trim((string)$v);

    // ext can be a flag or the actual external-id value
    $ext_raw = (string)($ext ?? '');
    $ext_is_value = ($ext_raw !== '' && !$is_booly($ext_raw));
    $ext_flag = $is_booly($ext_raw) ? !in_array($ext_raw, ['0','off'], true) : $ext_is_value;

    if ($v === '' && $ext_is_value) $v = trim($ext_raw);

    return [
      'p'   => $p,
      'v'   => $v,
      'ext' => $ext_flag,
      'ref' => isset($ref) && !in_array((string)$ref, ['0','off',''], true),
    ];
  };

  // rows: pv[0][p]...
  if (isset($pv_raw[0]) || (is_array(reset($pv_raw)) && array_key_exists('p', reset($pv_raw)))) {
    $out = [];
    foreach ($pv_raw as $row) $out[] = $mk($row['p'] ?? '', $row['v'] ?? '', $row['ext'] ?? '', $row['ref'] ?? '');
    return $out;
  }

  // parallel arrays: pv[p][], pv[v][], pv[ext][], pv[ref][]
  $P = $pv_raw['p']   ?? [];
  $V = $pv_raw['v']   ?? [];
  $E = $pv_raw['ext'] ?? [];
  $R = $pv_raw['ref'] ?? [];
  $n = max(count($P), count($V), count($E), count($R));
  $out = [];
  for ($i=0; $i<$n; $i++) $out[] = $mk($P[$i] ?? '', $V[$i] ?? '', $E[$i] ?? '', $R[$i] ?? '');
  return $out;
}

function _qs_norm_prop(string $p): ?string {
  $p = strtoupper(trim($p));
  if ($p === '') return null;
  if ($p[0] !== 'P') $p = 'P' . preg_replace('/\D+/', '', $p);
  return preg_match('/^P\d+$/', $p) ? $p : null;
}

function _qs_fmt_date(string $ymd): ?string {
  $ymd = trim($ymd);
  if (preg_match('/^\d{4}$/', $ymd))            return '+' . $ymd . '-00-00T00:00:00Z/9';
  if (preg_match('/^\d{4}-\d{2}$/', $ymd))      return '+' . $ymd . '-00T00:00:00Z/10';
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd))return '+' . $ymd . 'T00:00:00Z/11';
  return null;
}

function _qs_quote(string $s): string { return '"' . str_replace('"','\"',$s) . '"'; }

function _qs_fmt_value(string $v, bool $is_external_id=false): string {
  $v = trim($v);
  if ($v === '') return '';

  // Q-id
  if (preg_match('/^Q\d+$/i', $v)) return strtoupper($v);

  // Date (year, year-month, full date)
  if ($d = _qs_fmt_date($v)) return $d;

  // Bare number
  if (preg_match('/^-?\d+(\.\d+)?$/', $v)) return $v;

  // External ID  → always quoted
  if ($is_external_id) return _qs_quote($v);

  // URLs and generic strings  → quoted as well
  if (filter_var($v, FILTER_VALIDATE_URL)) return _qs_quote($v);

  return _qs_quote($v);
}


function appendProp($qid = null, $prop = null, $ref = null){
  $qs = '';
  if ($qid == null){
    $qid = "LAST";
  }
  $propLines = explode("\n", $prop);
  foreach ($propLines as $propLine) {
    if (trim($propLine) != ''){
      $qs .= "\n".$qid."|".$propLine.$ref;
    }
  }
  return $qs;

}


?>