<?php
/**
 * DEDICATIONS_SNIFFER.PHP — trace-through diagnostic for the BRAIN "Dedications" tester/handler dropdowns.
 *
 * WHAT IT DOES
 *   Given a part (or an explicit ENGR/ATOM tester+handler set), it replays exactly what happens when a user clicks a
 *   Planning-Assumptions row and chooses "Dedications", then reports whether each dropdown can actually preselect the
 *   stored ATOM value — and if not, WHY. It does this on two layers:
 *     LAYER 1 (ground truth): POSTs the real DEDICATIONS.PHP?PROCESS_TYPE=GET_TESTER_HANDLER with the exact payload the
 *              browser sends, and inspects the TESTER/HANDLER options it returns. This never drifts: it is the endpoint.
 *     LAYER 2 (diagnosis): calls MAPPER_EQUIP.PHP directly (NORMAL exact-match, then the DEDICATION fallback) to locate
 *              where the stored ATOM really lives, and to reconstruct the substring-fallback group DEDICATIONS would pick.
 *   It then classifies the outcome and, when Layer 2's reconstruction disagrees with Layer 1, prints a SYNC WARNING so
 *   you know the sniffer's copy of the logic has fallen behind the live code.
 *
 * HOW TO LAUNCH
 *   Browser / curl (resolve a part via the live grid feed):
 *     .../API/DEDICATIONS_SNIFFER.PHP?PART=LTC4286ACPCU-T0Z&SITE=ADGT&RES_AREA=ATE%20FT
 *   Explicit values (skip the grid lookup; useful for hypotheticals or if TOP_SECTION changes):
 *     ...?ENGR_TESTER=ETS364B_C4T&ENGR_HANDLER=RASCO1000_CP7_AHC&ATOM_TESTER=ETS364B_CP7_LTC428X_LT
 *        &ATOM_HANDLER=RASCO1000_CP7_LTC428X_F2&SITE=ADGT&RES_AREA=ATE%20FT
 *   Optional: &MODULE_TYPE=DEDICATION  (mimics the timephase-instance path), &STEP=FT1_FT (filter to one step).
 *   CLI (for local testing):  php DEDICATIONS_SNIFFER.PHP "PART=LTC4286ACPCU-T0Z&SITE=ADGT&RES_AREA=ATE FT"
 *
 * SYNCHRONICITY — this tool couples to code that changes over time. Each SYNC POINT below is where an upstream change
 * would require an edit here. The Layer1-vs-Layer2 self-check flags #1/#2 drift automatically; #3/#4 need eyes.
 *   #1 DEDICATIONS payload contract .. custom.js::getTesterHandler sends data=[[ENGR_TESTER],[ENGR_HANDLER],[RES_AREA]],
 *      site, module_type. Encoded in dedications_get().
 *   #2 GET_OTHER_TESTER_HANDLER switch .. the MAPPER param shapes (NORMAL uses ENG_CONFIG; GET_ENG_CONFIG uses RES_NM;
 *      default/DEDICATION uses neither) and the inner-break substring fallback. Encoded in mapper_param()/trace_side().
 *   #3 MAPPER ENG_ATOM output shape .. DATA[0][ENG_CONFIG][RES_NM] = {COUNT,CONFIG_PCNT,HAS_FUTURE_ALLOC}; RES_AREA
 *      "ATE X" -> WS_FT "X" (special "ATE PBS"->"CSP") happens server-side. Parsed in mapper_get().
 *   #4 Grid mapping .. TOP_SECTION MAIN_DISPLAY field names (ENGR_TESTER/ENGR_HANDLER/ATOM_TESTER/ATOM_HANDLER, SITE,
 *      TEST_TYPE, STEP) and the row[1]/[2]=ENGR, row[3]/[4]=ATOM, row[15] split, row[33]=RES_AREA indices. In resolve_examples().
 *   Hosts (also moving parts): DEDICATIONS on mxhD, MAPPER on afotcosj004, TOP_SECTION on mxhT — see $SNIFF below.
 *
 * SAFETY: read-only. Every call is a GET/POST to an existing read endpoint; no DB writes, no credentials read or stored.
 * 2026-08-11 RM (AI: Claude Code): authored end-to-end as a diagnostic companion to DEDICATIONS.PHP / MAPPER_EQUIP.PHP.
 */

if (php_sapi_name() === 'cli') {                                  // CLI convenience: php DEDICATIONS_SNIFFER.PHP "PART=..&SITE=..[&FORMAT=HTML]"
    if (isset($argv[1])) { parse_str($argv[1], $_GET); }
    $HTML = (isset($_GET['FORMAT']) && strtoupper($_GET['FORMAT']) === 'HTML');  // honor FORMAT=HTML from CLI too, so the styled page is testable locally
} else {
    // FORMAT=HTML renders a styled page for the in-app iframe (see DEDICATIONS.PHP "?" helper); default stays plain text.
    $HTML = (isset($_GET['FORMAT']) && strtoupper($_GET['FORMAT']) === 'HTML');
    header('Content-Type: '.($HTML ? 'text/html' : 'text/plain').'; charset=utf-8');
    // 2026-08-11 RM (AI: Claude Code): this is a live diagnostic — never let the browser or a proxy serve a stale render.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// In HTML mode we buffer every out() line, then wrap it in a styled <pre> with light colorizing of verdict lines.
$OUTBUF = array();

// Hosts under test. Keep in lockstep with BRAIN_UI_JCDP/requests/API.PHP + DEDICATIONS.PHP + MAPPER_EQUIP.PHP.
$SNIFF = array(
    'DEDICATIONS' => 'http://mxhdafot01l.maxim-ic.com/API/DEDICATIONS.PHP',   // endpoint under test (mxhD)
    'MAPPER'      => 'http://afotcosj004.maxim-ic.com/API/MAPPER_EQUIP.PHP',  // real data source (afotcosj004 -> testops EQUIP)
    'TOP_SECTION' => 'http://mxhtafot01l.maxim-ic.com/TEST/TOP_SECTION.php',  // grid MAIN_DISPLAY feed (mxhT); resolves PART -> rows
);

function out($s) { global $HTML, $OUTBUF; if ($HTML) { $OUTBUF[] = $s; } else { echo $s."\n"; } }
function q($s)   { return "'".$s."'"; }
function yn($b)  { return $b ? 'YES' : 'NO'; }
function hr($c)  { return str_repeat($c, 100); }
/** Compact one-word verdict tag for the plain-layer one-liner. */
function plain_tag($class) {
    $m = array('OK' => 'works', 'FALLBACK_BUG' => 'BROKEN (tool logic -> BRAIN programmer)',
               'EQUIP_GAP' => 'BROKEN (equipment data -> upstream owner)', 'NO_ATOM' => 'not checked',
               'INDETERMINATE' => 'UNCLEAR (-> BRAIN programmer)');
    return isset($m[$class]) ? $m[$class] : $class;
}

/** Single HTTP hop (GET, or POST when $post given). Returns body + wall-clock seconds + curl error. Read-only. */
function sniff_curl($url, $post = null) {
    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 120,                      // sniffer caps at 120s; prod DEDICATIONS uses TIMEOUT=>0 (uncapped) — we won't hang a trace
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    );
    if ($post !== null) { $opts[CURLOPT_CUSTOMREQUEST] = 'POST'; $opts[CURLOPT_POSTFIELDS] = http_build_query($post); }
    else                { $opts[CURLOPT_CUSTOMREQUEST] = 'GET'; }
    curl_setopt_array($curl, $opts);
    $t0 = microtime(true);
    $body = curl_exec($curl);
    $dt = microtime(true) - $t0;
    $err = curl_error($curl);
    curl_close($curl);
    return array('body' => $body, 'secs' => $dt, 'err' => $err);
}

/** Tolerant JSON decode: strips a leading PHP notice (DEDICATIONS) and unwraps a double-encoded JSON string (TOP_SECTION). */
function sniff_json($raw) {
    $s = trim($raw);
    $j = json_decode($s, true);
    if ($j === null) {                               // a PHP notice may precede the JSON; jump to the first {, [ or " and retry
        $pos = false;
        foreach (array('{', '[', '"') as $c) { $i = strpos($s, $c); if ($i !== false && ($pos === false || $i < $pos)) $pos = $i; }
        if ($pos !== false) { $j = json_decode(substr($s, $pos), true); }
    }
    if (is_string($j)) { $j = json_decode($j, true); }   // TOP_SECTION returns a JSON string CONTAINING JSON — decode twice
    return $j;
}

/** SYNC POINT #2: mirror DEDICATIONS.PHP::GET_OTHER_TESTER_HANDLER's switch($module_type) param builder, verbatim. */
function mapper_param($site, $res_type, $module_type, $res_area, $config = null) {
    switch ($module_type) {
        case 'NORMAL':
            return '[{ "SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"],"ENG_CONFIG":["'.$config.'"], "RES_AREA":["'.$res_area.'"] }]';
        case 'GET_ENG_CONFIG':
            return '[{ "SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"],"RES_NM":["'.$config.'"], "RES_AREA":["'.$res_area.'"] }]';
        default: // '' / 'DEDICATION'
            return '[{"SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"], "RES_AREA":["'.$res_area.'"] }]';
    }
}

/** One MAPPER_EQUIP ENG_ATOM call. Returns the DATA[0] map (ENG_CONFIG => RES_NM => meta), the param, and timing. SYNC POINT #3. */
function mapper_get($mapper, $site, $res_type, $module_type, $res_area, $config = null) {
    $param = mapper_param($site, $res_type, $module_type, $res_area, $config);
    $url = $mapper.'?INPUT_TYPE=JSON&OUTPUT_TYPE=ENG_ATOM&INPUT='.urlencode($param);
    $r = sniff_curl($url);
    $j = sniff_json($r['body']);
    $data = (is_array($j) && isset($j['DATA'][0]) && is_array($j['DATA'][0])) ? $j['DATA'][0] : null;
    $status = (is_array($j) && isset($j['STATUS'])) ? $j['STATUS'] : 'UNPARSED';
    return array('param' => $param, 'secs' => $r['secs'], 'data' => $data, 'status' => $status, 'raw' => $r['body']);
}

/** SYNC POINT #1: POST the real DEDICATIONS endpoint with the exact custom.js payload; return its decoded result (ground truth). */
function dedications_get($ded, $site, $engr_tester, $engr_handler, $res_area, $module_type) {
    $post = array(
        'data' => array(array($engr_tester), array($engr_handler), array($res_area)),  // data[0]=tester, data[1]=handler, data[2]=res_area
        'site' => $site,
        'module_type' => ($module_type === null ? '' : $module_type),
    );
    $r = sniff_curl($ded.'?PROCESS_TYPE=GET_TESTER_HANDLER', $post);
    return array('json' => sniff_json($r['body']), 'secs' => $r['secs'], 'raw' => $r['body']);
}

/** Pull the flat list of option ATOM_NAMEs the live endpoint returned for one side ('TESTER'|'HANDLER'), order-preserving. */
function endpoint_options($dedJson, $key) {
    $opts = array();
    if (is_array($dedJson) && isset($dedJson[$key]) && is_array($dedJson[$key])) {
        foreach ($dedJson[$key] as $row) { if (isset($row['ATOM_NAME']) && !in_array($row['ATOM_NAME'], $opts, true)) $opts[] = $row['ATOM_NAME']; }
    }
    return $opts;
}

/** SYNC POINT #4: resolve a part to its distinct grid rows via the live MAIN_DISPLAY feed (same call BRAIN's API.PHP makes). */
function resolve_examples($top, $part, $site, $res_area, $step) {
    $url = $top.'?INPUT_TYPE=MFG_PART_NUM&OUTPUT_TYPE=BODS_JDA_ADI&INPUT[0]='.urlencode($part);
    $r = sniff_curl($url);
    $j = sniff_json($r['body']);
    $rows = (is_array($j) && isset($j['DATA'])) ? $j['DATA'] : array();
    // Dedup on the tester/handler quartet only (SITE + ENGR/ATOM tester+handler) — NOT STEP. The dropdown behavior depends
    // solely on that quartet, so two steps sharing it (e.g. FT1_FT + QC1_FT) are ONE case; we collapse them and list the
    // steps together instead of emitting a duplicate example with the same ATOMs.
    $out = array(); $byKey = array();
    foreach ($rows as $d) {
        if (!isset($d['ENGR_TESTER'])) continue;
        if ($site     !== null && isset($d['SITE'])      && $d['SITE']      !== $site)     continue;
        if ($res_area !== null && isset($d['TEST_TYPE']) && $d['TEST_TYPE'] !== $res_area) continue;
        if ($step     !== null && isset($d['STEP'])      && $d['STEP']      !== $step)     continue;
        $k = $d['SITE'].'|'.$d['TEST_TYPE'].'|'.$d['ENGR_TESTER'].'|'.$d['ENGR_HANDLER'].'|'.$d['ATOM_TESTER'].'|'.$d['ATOM_HANDLER'];
        if (isset($byKey[$k])) {                         // same quartet seen: just record the extra step, keep one example row
            $i = $byKey[$k];
            if (!in_array($d['STEP'], $out[$i]['STEPS'], true)) $out[$i]['STEPS'][] = $d['STEP'];
            continue;
        }
        $byKey[$k] = count($out);
        $out[] = array('STEPS' => array($d['STEP']), 'SITE' => $d['SITE'], 'RES_AREA' => $d['TEST_TYPE'],
                       'ENGR_TESTER' => $d['ENGR_TESTER'], 'ENGR_HANDLER' => $d['ENGR_HANDLER'],
                       'ATOM_TESTER' => $d['ATOM_TESTER'], 'ATOM_HANDLER' => $d['ATOM_HANDLER']);
    }
    foreach ($out as &$o) { $o['STEP'] = implode(', ', $o['STEPS']); }  // flatten collected steps for display
    unset($o);
    return array('rows' => $out, 'secs' => $r['secs'], 'count_all' => count($rows));
}

/**
 * Analyze one side (TESTER or HANDLER) WITHOUT printing. $endpoint_opts = ATOM_NAMEs the LIVE endpoint returned for this side.
 * Returns a rich result array consumed by print_side_plain() (user layer) and print_side_detail() (technical layer).
 * CLASS is one of: OK | EQUIP_GAP (out of scope) | FALLBACK_BUG (DEDICATIONS logic) | NO_ATOM | INDETERMINATE (drift).
 */
function analyze_side($mapper, $label, $site, $res_area, $res_type, $engr_name, $atom_stored, $endpoint_opts) {
    // STEP A — NORMAL exact-match query: ENG_CONFIG = the ENGR name.
    $norm = mapper_get($mapper, $site, $res_type, 'NORMAL', $res_area, $engr_name);
    $normGroups = is_array($norm['data']) ? $norm['data'] : array();
    $normEmpty = (count($normGroups) === 0);
    $normHasAtom = false;
    foreach ($normGroups as $ec => $resnms) { if ($atom_stored !== '' && isset($resnms[$atom_stored])) $normHasAtom = true; }

    // STEP B — DEDICATION fallback query: no ENG_CONFIG filter (all configs for the site/res_area). Fetched regardless, to
    // (a) locate where the stored ATOM really lives and (b) reconstruct the substring-fallback group DEDICATIONS would pick.
    $ded = mapper_get($mapper, $site, $res_type, 'DEDICATION', $res_area, null);
    $dgroups = is_array($ded['data']) ? $ded['data'] : array();
    $atomGroups = array();
    foreach ($dgroups as $ec => $resnms) { if ($atom_stored !== '' && isset($resnms[$atom_stored])) $atomGroups[] = $ec; }

    // Reconstruct DEDICATIONS' inner-break loop: winner = LAST group with any RES_NM containing the ENGR name as a substring.
    $winner = ''; $cands = array();
    foreach ($dgroups as $ec => $resnms) {
        foreach ($resnms as $rn => $meta) { if (strpos($rn, $engr_name) !== false) { $cands[] = $ec; $winner = $ec; break; } }
    }
    $winnerResnms = ($winner !== '') ? array_keys($dgroups[$winner]) : array();
    $reachable = ($winner !== '' && $atom_stored !== '' && isset($dgroups[$winner][$atom_stored]));

    // STEP C — ground truth from the live endpoint, and our expectation from Layer 2.
    $endpointHas = ($atom_stored !== '' && in_array($atom_stored, $endpoint_opts, true));
    $expectedHas = ($normHasAtom || $reachable);

    // Classify.
    if ($atom_stored === '')                                            $class = 'NO_ATOM';
    else if ($endpointHas)                                              $class = 'OK';
    else if ($normEmpty && count($atomGroups) === 0)                    $class = 'EQUIP_GAP';
    else if ($normEmpty && count($atomGroups) > 0 && !$reachable)       $class = 'FALLBACK_BUG';
    else                                                                $class = 'INDETERMINATE';

    return array(
        'label' => $label, 'engr' => $engr_name, 'atom' => $atom_stored, 'site' => $site, 'res_area' => $res_area,
        'normParam' => $norm['param'], 'normSecs' => $norm['secs'], 'normStatus' => $norm['status'],
        'normGroups' => array_keys($normGroups), 'normEmpty' => $normEmpty, 'normHasAtom' => $normHasAtom,
        'dedParam' => $ded['param'], 'dedSecs' => $ded['secs'], 'dedGroupCount' => count($dgroups),
        'atomGroups' => $atomGroups, 'cands' => $cands, 'winner' => $winner, 'winnerResnms' => $winnerResnms,
        'reachable' => $reachable, 'endpointOpts' => $endpoint_opts, 'endpointHas' => $endpointHas,
        'expectedHas' => $expectedHas, 'class' => $class,
        'syncWarn' => ($atom_stored !== '' && $endpointHas !== $expectedHas),
    );
}

/** LAYER 1 — plain-language verdict + next step for a reader who only knows ENG_* / ATOM_* names. */
function print_side_plain($R) {
    $verdict = array('OK' => '[OK]  WORKS', 'FALLBACK_BUG' => '[X]  BROKEN', 'EQUIP_GAP' => '[X]  BROKEN',
                     'NO_ATOM' => '[-]  NOT CHECKED', 'INDETERMINATE' => '[?]  UNCLEAR');
    out("  ".$R['label']." dropdown");
    out("     Value it must pre-select : ".($R['atom'] === '' ? '(none provided)' : $R['atom'])."   <- ATOM (the saved setup)");
    out("     Looked up in the tool as : ".$R['engr']."   <- ENGR (what DEDICATIONS searches on)");
    out("     Result                   : ".$verdict[$R['class']]);
    switch ($R['class']) {
        case 'OK':
            out("     Why                      : The ENGR name is a recognized configuration and its option list includes the");
            out("                                saved ATOM value, so the dropdown shows it and pre-selects it.");
            out("     What to do               : Nothing — this dropdown is fine.");
            break;
        case 'FALLBACK_BUG':
            out("     Why                      : The ENGR name ".q($R['engr'])." is NOT a recognized configuration on its own. The tool");
            out("                                then guesses a similarly-named configuration (".q($R['winner'])."), and that guess does");
            out("                                not contain the saved value. The saved value DOES exist in the system — just under");
            out("                                other configurations the guess never looks at:");
            out("                                  ".implode(', ', $R['atomGroups']));
            out("     What to do               : This is a tool-logic limitation, not bad data. CONSULT THE BRAIN PROGRAMMER to make");
            out("                                DEDICATIONS match on the saved ATOM value directly (or list it regardless). Until then");
            out("                                this dropdown cannot pre-select the saved value.");
            break;
        case 'EQUIP_GAP':
            out("     Why                      : The saved value ".q($R['atom'])." does not exist ANYWHERE in the equipment data for this");
            out("                                site / test area. It was likely renamed, retired, or has not been loaded yet.");
            out("     What to do               : This is upstream EQUIPMENT data, outside DEDICATIONS. Confirm the ATOM name is current;");
            out("                                if it should exist, raise it with the equipment-data owner. A BRAIN programmer can");
            out("                                confirm the gap but cannot fix it from the UI.");
            break;
        case 'NO_ATOM':
            out("     Why                      : No saved ATOM value was supplied for this side, so there is nothing to pre-select.");
            out("     What to do               : Re-run with the ATOM_".strtoupper($R['label'])."= value if you want this side checked.");
            break;
        default: // INDETERMINATE
            out("     Why                      : The tool's own reasoning disagrees with what the live page returned (see SYNC WARNING");
            out("                                in the detail below). The result is not trustworthy right now.");
            out("     What to do               : CONSULT THE BRAIN PROGRAMMER — this sniffer's logic has likely fallen behind the live code.");
            break;
    }
}

/** LAYER 2 — the full technical trace (the A/B/C/D steps), for a reader who wants the underlying queries + reasoning. */
function print_side_detail($R) {
    out("");
    out("  ".hr('-'));
    out("  [".$R['label']."]  ENGR (query input) = ".q($R['engr'])."   |   ATOM (stored / to-preselect) = ".q($R['atom']));
    out("  ".hr('-'));

    out(sprintf("    A) NORMAL  (%.2fs)  param=%s", $R['normSecs'], $R['normParam']));
    if ($R['normEmpty']) {
        out("       -> EMPTY: no ENG_CONFIG is literally named ".q($R['engr'])." for SITE=".$R['site']." / ".$R['res_area']."  (status=".$R['normStatus'].")");
    } else {
        out("       -> ".count($R['normGroups'])." config group(s): ".implode(', ', $R['normGroups']));
        out("       stored ATOM present in NORMAL result? ".yn($R['normHasAtom']));
    }

    out(sprintf("    B) DEDICATION fallback  (%.2fs)  param=%s", $R['dedSecs'], $R['dedParam']));
    out("       -> ".$R['dedGroupCount']." config group(s) total for SITE=".$R['site']." / ".$R['res_area']);
    out("       stored ATOM ".q($R['atom'])." lives under group(s): ".(count($R['atomGroups']) ? implode(', ', $R['atomGroups']) : 'NONE'));
    out("       substring fallback on ENGR ".q($R['engr']).": candidates=[".implode(', ', $R['cands'])."]  winner=".q($R['winner']));
    if ($R['winner'] !== '') out("         winner ".q($R['winner'])." RES_NM set: ".implode(', ', $R['winnerResnms']));

    out("    C) LIVE endpoint dropdown options: ".(count($R['endpointOpts']) ? implode(', ', $R['endpointOpts']) : '(none)'));
    out("       >>> stored ATOM selectable in LIVE dropdown? ".yn($R['endpointHas']));

    out("    D) DIAGNOSIS:");
    switch ($R['class']) {
        case 'OK':
            out("       OK — the stored ATOM is a selectable option; this dropdown preselects correctly (custom.js sets option[value].selected).");
            break;
        case 'EQUIP_GAP':
            out("       ROOT CAUSE OUTSIDE DEDICATIONS/DROPDOWN SCOPE — the stored ATOM ".q($R['atom'])." does not exist anywhere in");
            out("       MAPPER for SITE=".$R['site']." / ".$R['res_area'].". This is an EQUIP data/mapping gap (CONFIGID_TO_SITENUM_RESNM / V_UDF_ENG_CONFIG /");
            out("       ENG_ATOM) or a stale/renamed ATOM. Nothing DEDICATIONS or the dropdown code can do until the source has it.");
            break;
        case 'FALLBACK_BUG':
            out("       ROOT CAUSE IN DEDICATIONS FALLBACK — the ENGR name ".q($R['engr'])." is not a valid ENG_CONFIG (NORMAL was empty), so");
            out("       the substring fallback bound to config ".q($R['winner']).", which does NOT carry the stored ATOM. The ATOM does exist under");
            out("       [".implode(', ', $R['atomGroups'])."] but the fallback cannot reach it. Fix belongs in DEDICATIONS (match on the stored ATOM,");
            out("       i.e. table_row[3]/[4], instead of a substring of the ENGR name) or inject the stored ATOM as a forced option.");
            break;
        case 'NO_ATOM':
            out("       NOT CHECKED — no stored ATOM supplied for this side, so selectability is undefined.");
            break;
        default:
            out("       INDETERMINATE / possible logic drift — see SYNC CHECK below.");
            break;
    }

    if ($R['syncWarn']) {
        out("    !! SYNC WARNING: reconstruction expected selectable=".yn($R['expectedHas'])." but the LIVE endpoint says ".yn($R['endpointHas']).".");
        out("       DEDICATIONS.PHP or MAPPER_EQUIP.PHP has likely changed. Trust the LIVE result; update SYNC POINT #1/#2 in this file.");
    }
}

// -------------------------------------------------------- main --------------------------------------------------------
$G = $_GET;
$SITE     = isset($G['SITE'])     && $G['SITE']     !== '' ? $G['SITE']     : null;
$RES_AREA = isset($G['RES_AREA']) && $G['RES_AREA'] !== '' ? $G['RES_AREA'] : null;
$STEP     = isset($G['STEP'])     && $G['STEP']     !== '' ? $G['STEP']     : null;
$MODULE   = isset($G['MODULE_TYPE']) && $G['MODULE_TYPE'] !== '' ? $G['MODULE_TYPE'] : null;
$PART     = isset($G['PART'])     && $G['PART']     !== '' ? $G['PART']     : null;

out(hr('='));
out("DEDICATIONS DROPDOWN SNIFFER");
out("  DEDICATIONS : ".$SNIFF['DEDICATIONS']);
out("  MAPPER      : ".$SNIFF['MAPPER']);
out("  TOP_SECTION : ".$SNIFF['TOP_SECTION']);
out("  module_type : ".($MODULE === null ? '(normal / null)' : $MODULE));
out(hr('='));

// Resolve the example set: either from an explicit ENGR/ATOM quartet, or by looking the part up in the live grid feed.
$examples = array();
if (isset($G['ENGR_TESTER']) || isset($G['ENGR_HANDLER'])) {
    if ($SITE === null || $RES_AREA === null) { out("ERROR: explicit mode needs SITE and RES_AREA."); exit; }
    $exStep = isset($G['STEP']) ? $G['STEP'] : '(explicit)';
    $examples[] = array(
        'STEP' => $exStep, 'STEPS' => array($exStep), 'SITE' => $SITE, 'RES_AREA' => $RES_AREA,
        'ENGR_TESTER'  => isset($G['ENGR_TESTER'])  ? $G['ENGR_TESTER']  : '',
        'ENGR_HANDLER' => isset($G['ENGR_HANDLER']) ? $G['ENGR_HANDLER'] : '',
        'ATOM_TESTER'  => isset($G['ATOM_TESTER'])  ? $G['ATOM_TESTER']  : '',
        'ATOM_HANDLER' => isset($G['ATOM_HANDLER']) ? $G['ATOM_HANDLER'] : '',
    );
} else if ($PART !== null) {
    $res = resolve_examples($SNIFF['TOP_SECTION'], $PART, $SITE, $RES_AREA, $STEP);
    out(sprintf("Resolved PART %s via TOP_SECTION (%.2fs): %d raw rows -> %d distinct tester/handler combo(s)%s.",
        q($PART), $res['secs'], $res['count_all'], count($res['rows']),
        ($SITE || $RES_AREA || $STEP) ? " after SITE/RES_AREA/STEP filter" : ""));
    $examples = $res['rows'];
    if (count($examples) === 0) { out("No matching grid rows. Check PART/SITE/RES_AREA/STEP, or that TOP_SECTION still returns these fields (SYNC #4)."); exit; }
} else {
    out("USAGE: ?PART=<mfg_part_num>[&SITE=..&RES_AREA=..&STEP=..]   OR   ?ENGR_TESTER=..&ENGR_HANDLER=..&ATOM_TESTER=..&ATOM_HANDLER=..&SITE=..&RES_AREA=..");
    out("       optional &MODULE_TYPE=DEDICATION to mimic the timephase-instance path.");
    exit;
}

$idx = 0; $anyBroken = false;
foreach ($examples as $ex) {
    $idx++;
    out("");
    out(hr('#'));
    $stepCount = (isset($ex['STEPS']) && is_array($ex['STEPS'])) ? count($ex['STEPS']) : 1;
    out(sprintf("EXAMPLE %d/%d   STEP%s=%s   SITE=%s   RES_AREA=%s",
        $idx, count($examples), ($stepCount > 1 ? "S(".$stepCount.")" : ""), $ex['STEP'], $ex['SITE'], $ex['RES_AREA']));
    out("   ENGR_TESTER =".q($ex['ENGR_TESTER'])."   ATOM_TESTER =".q($ex['ATOM_TESTER']));
    out("   ENGR_HANDLER=".q($ex['ENGR_HANDLER'])."   ATOM_HANDLER=".q($ex['ATOM_HANDLER']));
    out(hr('#'));

    // LAYER 1 — one live DEDICATIONS round-trip (this is what the browser gets; also doubles as a runtime measurement).
    $dg = dedications_get($SNIFF['DEDICATIONS'], $ex['SITE'], $ex['ENGR_TESTER'], $ex['ENGR_HANDLER'], $ex['RES_AREA'], $MODULE);
    out(sprintf("LIVE DEDICATIONS GET_TESTER_HANDLER round-trip: %.2fs", $dg['secs']));
    if (!is_array($dg['json'])) {
        out("!! LIVE endpoint did not return parseable JSON. First 400 bytes of body follow (SYNC #1, or endpoint error):");
        out("   ".substr(trim($dg['raw']), 0, 400));
        continue;
    }
    $th = isset($dg['json']['TESTER_HEADER'])  ? $dg['json']['TESTER_HEADER']  : array();
    $hh = isset($dg['json']['HANDLER_HEADER']) ? $dg['json']['HANDLER_HEADER'] : array();
    $testerOpts  = endpoint_options($dg['json'], 'TESTER');
    $handlerOpts = endpoint_options($dg['json'], 'HANDLER');

    // Compute both sides once (no printing); the two layers below render the same result at different depths.
    $rt = analyze_side($SNIFF['MAPPER'], 'TESTER',  $ex['SITE'], $ex['RES_AREA'], 'TESTER',  $ex['ENGR_TESTER'],  $ex['ATOM_TESTER'],  $testerOpts);
    $rh = analyze_side($SNIFF['MAPPER'], 'HANDLER', $ex['SITE'], $ex['RES_AREA'], 'HANDLER', $ex['ENGR_HANDLER'], $ex['ATOM_HANDLER'], $handlerOpts);

    // ============ LAYER 1: PLAIN-LANGUAGE (read this to decide what to action) ============
    out("");
    out("  ".str_repeat('=', 96));
    out("  WHAT HAPPENED  (plain language — ENGR = engineering name searched on, ATOM = the saved value to pre-select)");
    out("  ".str_repeat('=', 96));
    print_side_plain($rt);
    out("");
    print_side_plain($rh);
    out("");
    out("  ONE-LINE VERDICT: TESTER ".plain_tag($rt['class'])."   |   HANDLER ".plain_tag($rh['class']));

    // ============ LAYER 2: DETAILED REASONING (the underlying queries + trace) ============
    out("");
    out("  ".str_repeat('.', 96));
    out("  DETAILED REASONING  (live round-trip ".sprintf('%.2fs', $dg['secs'])."; MAPPER queries + fallback reconstruction)");
    out("  ".str_repeat('.', 96));
    out("    Live DEDICATIONS headers: TESTER_HEADER=[".implode(', ', $th)."]  HANDLER_HEADER=[".implode(', ', $hh)."]");
    print_side_detail($rt);
    print_side_detail($rh);

    out("");
    out("  SUMMARY: tester selectable=".yn($rt['endpointHas'])."   handler selectable=".yn($rh['endpointHas']));
    if (!$rt['endpointHas'] || !$rh['endpointHas']) $anyBroken = true;
}

out("");
out(hr('='));
out($anyBroken ? "RESULT: at least one dropdown could NOT preselect its stored ATOM — see per-side DIAGNOSIS above."
               : "RESULT: all traced dropdowns preselect their stored ATOM correctly.");
out(hr('='));

// ---- HTML wrap: flush the buffered plain-text output into a styled, lightly-colorized page for the in-app iframe. ----
if ($HTML) {
    $css = 'body{margin:0;background:#1e1e1e;color:#d4d4d4;font:12px/1.5 Consolas,"Courier New",monospace;}'
         .'pre{margin:0;padding:14px 16px;white-space:pre-wrap;word-break:break-word;}'
         .'.ok{color:#4ec9b0;}.bad{color:#f48771;font-weight:bold;}.warn{color:#dcdcaa;}'
         .'.hd{color:#569cd6;}.dim{color:#808080;}';
    // Per-fragment classifier: pick the class from a single fragment's keywords (bad > ok > warn), '' if none apply.
    $frag_cls = function($s) {
        if (strpos($s,'[X]')!==false || strpos($s,'BROKEN')!==false || strpos($s,'ROOT CAUSE')!==false) return 'bad';
        if (strpos($s,'[OK]')!==false || stripos($s,'works')!==false || strpos($s,'preselects correctly')!==false) return 'ok';
        if (strpos($s,'SYNC WARNING')!==false || strpos($s,'CONSULT')!==false || strpos($s,'UNCLEAR')!==false) return 'warn';
        return '';
    };
    $html = '';
    foreach ($OUTBUF as $line) {
        // SUMMARY lines carry BOTH a tester and a handler verdict; coloring the whole line by the first keyword would paint
        // "TESTER works" red just because "HANDLER BROKEN" shares the line. Split on the pipe and color each half on its own.
        if (strpos($line, 'ONE-LINE VERDICT') !== false) {
            $parts = explode('|', $line);
            $seg = array();
            foreach ($parts as $p) { $c = $frag_cls($p); $ep = htmlspecialchars($p, ENT_QUOTES); $seg[] = ($c ? '<span class="'.$c.'">'.$ep.'</span>' : $ep); }
            $html .= implode('|', $seg)."\n";
            continue;
        }
        $e = htmlspecialchars($line, ENT_QUOTES);
        $cls = '';
        if (strpos($line, '[X]') !== false || strpos($line, 'BROKEN') !== false || strpos($line, 'ROOT CAUSE') !== false) $cls = 'bad';
        else if (strpos($line, '[OK]') !== false || strpos($line, 'WORKS') !== false || strpos($line, 'preselects correctly') !== false) $cls = 'ok';
        else if (strpos($line, 'SYNC WARNING') !== false || strpos($line, 'CONSULT') !== false || strpos($line, 'UNCLEAR') !== false) $cls = 'warn';
        else if (strpos($line, 'WHAT HAPPENED') !== false || strpos($line, 'DETAILED REASONING') !== false) $cls = 'hd';
        $html .= ($cls ? '<span class="'.$cls.'">'.$e.'</span>' : $e)."\n";
    }
    // 2026-08-11 RM (AI: Claude Code): this page is shown in a CROSS-ORIGIN iframe (mxhD) inside the parent modal (mxhT). When
    // focus is in here, the parent's Bootstrap Esc handler can't see the keypress, so Esc appeared dead. Relay Esc up to the
    // parent via postMessage (parent listens for type DEDICATION_SNIFFER_ESC and closes the modal). Auto-focus so Esc works
    // immediately without a click. targetOrigin '*' is safe here: the payload is a fixed sentinel with no sensitive data.
    $js = 'document.addEventListener("keydown",function(e){if(e.key==="Escape"||e.keyCode===27){'
        .'try{parent.postMessage({type:"DEDICATION_SNIFFER_ESC"},"*");}catch(err){}}});'
        .'window.focus();';
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Dedications dropdown check</title><style>$css</style></head>"
        ."<body><pre>$html</pre><script>$js</script></body></html>";
}
?>
