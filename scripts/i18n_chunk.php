<?php
/**
 * Build the translation work list: every gap a locale has, split into chunks an
 * agent can do in one go.
 *
 *   php scripts/i18n_chunk.php hi                    one locale
 *   php scripts/i18n_chunk.php hi bn ta --max 260    several, custom chunk size
 *   php scripts/i18n_chunk.php --indian              the nine Indian locales
 *   php scripts/i18n_chunk.php hi --out C:/tmp/x     somewhere other than the default
 *
 * 🔑 CHUNKS ARE SPLIT BY TOP-LEVEL SECTION, not by counting to N and cutting.
 * A section is a screen, so a unit of work is coherent — the agent sees all the
 * strings for one dialogue at once and can make them read as a set. It also
 * means a failure loses a screen rather than half a module.
 *
 * ⚠️ A section larger than --max is NOT split. Cutting a screen in half to hit
 * an arbitrary number is how you get two halves translated in two registers.
 * The oversized ones are reported so the orchestrator can give them their own
 * agent rather than discovering it at merge time.
 *
 * Writes, under --out (default: the session's own directory):
 *   chunks/<locale>__<namespace>__<section>.en.tsv   the English to translate
 *   _worklist.json                                   what to fan out over
 *
 * Read-only with respect to lang/. Writes nothing but chunk files.
 */

require __DIR__ . '/i18n_lib.php';

$root = dirname(__DIR__);
chdir($root);

$argvRest = array_slice($argv, 1);
$locales = [];
$max = 260;

/**
 * 🔴 BYTES ARE THE REAL LIMIT, NOT KEYS. Measured on the French run of
 * 2026-09-22, in the units i18nRowBytes() counts (key + tab + value + newline):
 *
 *   contracts.rfp   597 keys, 39,847 bytes, short labels   -> COMPLETED, ~2 min
 *   tickets.settings 629 keys, 59,934 bytes, mixed          -> AGENT KILLED
 *   tickets.help    358 keys, 63,787 bytes, help prose      -> AGENT KILLED
 *
 * Both deaths were the 64,000 output-token ceiling, and both agents were killed
 * before writing anything at all. Key count predicted neither: the 597-key
 * chunk survived and the 358-key one did not. Size predicted both.
 *
 * ⚠️ SO THE TRUE THRESHOLD IS ONLY KNOWN TO LIE BETWEEN 40 KB AND 60 KB, and
 * it is not a clean line — prose is more expensive per byte than labels,
 * because the agent reasons more per line and the translation itself runs
 * 15-20% longer than its English. 24 KB sits 40% under the largest chunk known
 * to survive and 60% under the smallest known to die.
 *
 * Do not raise this to squeeze out a few agents. A chunk that dies costs a full
 * re-run and a manual split; a chunk that is too small costs one more agent
 * re-reading the brief. Those are not comparable.
 */
$maxBytes = 24000;
$out = $root . '/.i18n-work';

for ($i = 0; $i < count($argvRest); $i++) {
    $a = $argvRest[$i];
    if ($a === '--max') { $max = max(20, (int)$argvRest[++$i]); continue; }
    if ($a === '--maxbytes') { $maxBytes = max(2000, (int)$argvRest[++$i]); continue; }
    if ($a === '--out') { $out = rtrim($argvRest[++$i], '/\\'); continue; }
    if ($a === '--indian') {
        $locales = array_merge($locales, ['hi','bn','ta','te','mr','pa','gu','kn','ml']);
        continue;
    }
    if (strpos($a, '--') === 0) { fwrite(STDERR, "unknown option $a\n"); exit(2); }
    $locales[] = $a;
}
$locales = array_values(array_unique($locales));
if (!$locales) {
    fwrite(STDERR, "usage: i18n_chunk.php <locale>... | --indian  [--max N] [--out DIR]\n");
    exit(2);
}

$enFiles = glob('lang/en/*.php');
sort($enFiles);

$worklist = [];
$oversized = [];
$totalKeys = 0;
$chunkDir = $out . '/chunks';
@mkdir($chunkDir, 0777, true);

foreach ($locales as $loc) {
    if (!is_dir("lang/$loc")) { fwrite(STDERR, "no such locale dir: lang/$loc\n"); exit(2); }

    foreach ($enFiles as $enPath) {
        $ns   = basename($enPath, '.php');
        $en   = i18nFlatten(i18nLoad($enPath));
        $have = i18nFlatten(i18nLoad("lang/$loc/$ns.php"));

        // The gap: keys English has that this locale does not. An existing
        // value is never re-translated, however poor — that is a review job,
        // not a gap-filling job, and conflating the two loses work.
        $gap = array_diff_key($en, $have);
        if (!$gap) continue;

        $whole = !is_file("lang/$loc/$ns.php");

        // Group the gap by its top-level section.
        $sections = [];
        foreach ($gap as $k => $v) {
            $top = strpos($k, I18N_SEP) === false ? '_root' : substr($k, 0, strpos($k, I18N_SEP));
            $sections[$top][$k] = $v;
        }

        // Pack whole sections together up to --max keys AND --maxbytes bytes.
        $batches = [];
        $cur = []; $curN = 0; $curB = 0; $curNames = [];
        foreach ($sections as $name => $rows) {
            $n = count($rows);
            $b = i18nRowBytes($rows);

            if ($n > $max || $b > $maxBytes) {
                $oversized[] = ['locale'=>$loc, 'ns'=>$ns, 'section'=>$name, 'keys'=>$n, 'bytes'=>$b];
                if ($cur) { $batches[] = [$curNames, $cur]; $cur = []; $curN = 0; $curB = 0; $curNames = []; }

                // ⚠️ A section over the BYTE limit must be split or its agent dies
                // with nothing written — so split it at SECOND-level key boundaries,
                // which keeps each dialogue or panel whole. A section that is merely
                // over the KEY limit still gets one agent, as it always did.
                if ($b > $maxBytes) {
                    foreach (i18nSplitSection($rows, $maxBytes) as $pi => $part) {
                        $batches[] = [[$name . '_p' . ($pi + 1)], $part];
                    }
                } else {
                    $batches[] = [[$name], $rows];    // its own agent
                }
                continue;
            }

            if (($curN + $n > $max || $curB + $b > $maxBytes) && $cur) {
                $batches[] = [$curNames, $cur]; $cur = []; $curN = 0; $curB = 0; $curNames = [];
            }
            foreach ($rows as $k => $v) $cur[$k] = $v;
            $curN += $n; $curB += $b; $curNames[] = $name;
        }
        if ($cur) $batches[] = [$curNames, $cur];

        foreach ($batches as $idx => [$names, $rows]) {
            $label = count($names) === 1 ? $names[0] : ('mixed' . ($idx + 1));
            $label = preg_replace('/[^A-Za-z0-9_-]/', '_', $label);
            $file  = "{$loc}__{$ns}__{$label}.en.tsv";
            i18nWriteTsv("$chunkDir/$file", $rows);
            $worklist[] = [
                'locale'    => $loc,
                'namespace' => $ns,
                'section'   => $label,
                'sections'  => $names,
                'keys'      => count($rows),
                'bytes'     => i18nRowBytes($rows),
                'whole_file'=> $whole,
                'en_tsv'    => "chunks/$file",
                'out_tsv'   => 'chunks/' . str_replace('.en.tsv', ".$loc.tsv", $file),
            ];
            $totalKeys += count($rows);
        }
    }
}

// Biggest first: a 1,700-key module wants a concurrency slot early, not last.
usort($worklist, function ($a, $b) { return $b['keys'] <=> $a['keys']; });

file_put_contents($out . '/_worklist.json', json_encode([
    'generated'   => gmdate('c'),
    'locales'     => $locales,
    'max_chunk'   => $max,
    'max_bytes'   => $maxBytes,
    'total_keys'  => $totalKeys,
    'total_chunks'=> count($worklist),
    'oversized'   => $oversized,
    'items'       => $worklist,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

printf("locales      : %s\n", implode(' ', $locales));
printf("chunks       : %d\n", count($worklist));
printf("keys         : %s\n", number_format($totalKeys));
printf("max/chunk    : %d keys, %s bytes\n", $max, number_format($maxBytes));
printf("out          : %s\n", $out);
if ($oversized) {
    printf("\noversized sections:\n");
    foreach (array_slice($oversized, 0, 12) as $o) {
        printf("  %-3s %-20s %-24s %5d keys %7s bytes  %s\n",
            $o['locale'], $o['ns'], $o['section'], $o['keys'], number_format($o['bytes']),
            $o['bytes'] > $maxBytes ? 'SPLIT at second-level boundaries' : 'own agent, not split');
    }
    if (count($oversized) > 12) printf("  ... and %d more\n", count($oversized) - 12);
}

// The number that actually predicts whether an agent survives. A chunk over the
// byte limit here is one that should have been split and was not — which means a
// single second-level group is itself too big and wants a human.
$worst = 0;
foreach ($worklist as $w) $worst = max($worst, $w['bytes']);
printf("\nlargest chunk: %s bytes (limit %s)%s\n",
    number_format($worst), number_format($maxBytes),
    $worst > $maxBytes ? '   ⚠️ OVER - an agent may die on it' : '   ok');
printf("\nper-locale totals:\n");
$byLoc = [];
foreach ($worklist as $w) { $byLoc[$w['locale']]['k'] = ($byLoc[$w['locale']]['k'] ?? 0) + $w['keys']; $byLoc[$w['locale']]['c'] = ($byLoc[$w['locale']]['c'] ?? 0) + 1; }
foreach ($byLoc as $l => $t) printf("  %-3s %5s keys in %3d chunks\n", $l, number_format($t['k']), $t['c']);
