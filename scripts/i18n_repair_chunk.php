<?php
/**
 * i18n repair — fix the one malformation that keeps coming back, and only that one.
 *
 *   php scripts/i18n_repair_chunk.php pl                     every pl chunk in .i18n-work
 *   php scripts/i18n_repair_chunk.php gu .i18n-work-2/chunks somewhere else
 *   php scripts/i18n_repair_chunk.php pl --dry-run           report, change nothing
 *
 * ──────────────────────────────────────────────────────────────────────────
 * 🔴 WHY THIS EXISTS, AND WHY IT IS NOT A BETTER WARNING.
 *
 * A translation chunk is `key<TAB>value` per line. When the ENGLISH VALUE IS
 * EMPTY — a blank column header, a hint deliberately left unset — the correct
 * output is `key`, a tab, and nothing. There is nothing after the tab to make
 * the tab look necessary, so it gets dropped, and the line becomes a bare key
 * that the merge tool would silently discard.
 *
 * This happened FIVE times in one day, across five different agents, in two
 * languages. Every time on a blank-English key. Every time the agent's own
 * self-check reported success. The last one had a prompt explicitly naming the
 * failure, stating it had already happened four times, and instructing the agent
 * to grep its own output for tabless lines. It still happened.
 *
 * 🔑 So the conclusion is not "warn harder". It is that a tab followed by
 * nothing is not reliably producible, and the pipeline should repair it rather
 * than keep asking. There is no translation judgement involved: if English is
 * empty the translation is empty, necessarily and in every language.
 *
 * ⚠️ THE REFUSAL IS THE IMPORTANT HALF. A missing tab on a key whose English is
 * NOT empty means the agent lost a translation. Inserting a blank there would
 * convert a loud, catchable failure into a silent one — an empty string that
 * renders as nothing and never falls back to English. So that case is reported
 * and left alone, and the chunk still fails verification.
 *
 * There are currently 7 blank-valued keys in lang/en. Each is a landmine for
 * each of the 23 locales, so this runs before verification, every time.
 * ──────────────────────────────────────────────────────────────────────────
 */

$args = array_slice($argv, 1);
$dry  = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--dry-run'));

$loc = $args[0] ?? '';
if ($loc === '') {
    fwrite(STDERR, "usage: php scripts/i18n_repair_chunk.php <locale> [chunks-dir] [--dry-run]\n");
    exit(2);
}
$root = dirname(__DIR__);
$dir  = rtrim($args[1] ?? ($root . '/.i18n-work/chunks'), '/\\') . '/';

if (!is_dir($dir)) { fwrite(STDERR, "no such directory: $dir\n"); exit(2); }

$fixed = $refused = $files = 0;

foreach (glob($dir . "*.$loc.tsv") as $t) {
    $en = preg_replace('/\.' . preg_quote($loc, '/') . '\.tsv$/', '.en.tsv', $t);
    if (!file_exists($en)) continue;

    // English key => value, so we can tell "legitimately blank" from "lost".
    $enMap = [];
    foreach (file($en, FILE_IGNORE_NEW_LINES) as $l) {
        if ($l === '') continue;
        $p = explode("\t", $l, 2);
        $enMap[$p[0]] = $p[1] ?? '';
    }

    $lines   = file($t, FILE_IGNORE_NEW_LINES);
    $out     = [];
    $touched = false;

    foreach ($lines as $n => $l) {
        if ($l !== '' && strpos($l, "\t") === false) {
            $key = $l;
            if (array_key_exists($key, $enMap) && $enMap[$key] === '') {
                $out[] = $key . "\t";
                $touched = true;
                $fixed++;
                printf("  %s  %-40s line %-4d %s\n", $dry ? 'would fix' : '  fixed  ',
                    basename($t), $n + 1, $key);
                continue;
            }
            $refused++;
            printf("  REFUSED   %-40s line %-4d %s\n", basename($t), $n + 1, $key);
            echo "            English is NOT empty for this key - a translation was lost.\n";
            echo "            Left as-is so verification still fails. Re-translate the chunk.\n";
        }
        $out[] = $l;
    }

    if ($touched && !$dry) {
        file_put_contents($t, implode("\n", $out) . "\n");
        $files++;
    }
}

printf("\n%s%d line(s), %d refused%s\n",
    $dry ? 'would repair ' : 'repaired ', $fixed, $refused,
    $dry ? '  (dry run - nothing written)' : ", across $files file(s)");

// A refusal is a real problem, so say so in the exit code too.
exit($refused > 0 ? 1 : 0);
