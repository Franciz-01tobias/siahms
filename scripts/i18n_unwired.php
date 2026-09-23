<?php
/**
 * Find user-facing screens that render text but never call t().
 *
 *   php scripts/i18n_unwired.php            report, exit 1 if anything is unwired
 *   php scripts/i18n_unwired.php --all      list every scanned file and its score
 *
 * ──────────────────────────────────────────────────────────────────────────
 * 🔴 THE DEFECT THIS CATCHES IS INVISIBLE TO EVERY OTHER CHECK WE HAVE.
 *
 * `i18n_audit.php` compares each locale against `lang/en`. That is the right
 * question and it cannot see this one, because the failure is a gap in
 * `lang/en` ITSELF — a screen whose strings were never added to it.
 *
 * Found in the running product by the maintainer, not by tooling: the Spanish
 * Checklists screen was in English while the audit reported Spanish at 100%.
 * Both facts were true. `lang/en/checklists.php` held 70 help-page keys and 3
 * nav items, and the module's own screens — 2,011 lines across four files —
 * contained no t() call at all. Its help page had been internationalised and
 * the module never had been, so all 24 locales rendered it in English.
 *
 * 🔑 A locale cannot be more complete than its source. Coverage of `lang/en`
 * says nothing about coverage of the PRODUCT, and only this check asks that.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * ⚠️ IT FAILS ONLY ON THE UNAMBIGUOUS CASE: a file with several visible
 * strings and ZERO t() calls. A half-wired file is reported as a warning, not
 * a failure, because "some strings are hardcoded" is a judgement about which
 * ones, and a checker that argues about judgement gets switched off. Prefer
 * missing an exotic case to crying wolf on a common one.
 */

$root = dirname(__DIR__);
chdir($root);

$all  = in_array('--all', array_slice($argv, 1), true);

/** A file needs at least this many visible strings before a zero score is damning. */
const MIN_STRINGS = 5;

/**
 * Directories that never render a screen. `api/` returns JSON, `includes/`
 * holds logic — but NOT `<module>/includes/`, which holds page headers that do
 * render, so the exclusion is anchored to the start of the path.
 */
const SKIP = ['api/', 'includes/', 'vendor/', 'node_modules/', 'tests/', 'scripts/', 'lang/', 'cron/', 'assets/vendor/'];

function skipped(string $p): bool
{
    foreach (SKIP as $s) if (str_starts_with($p, $s)) return true;
    return str_contains($p, '/vendor/') || str_contains($p, '.min.js');
}

/**
 * Count t(...) and window.t(...) calls — plus calls to any LOCAL WRAPPER
 * around window.t that the file defines for itself.
 *
 * ⚠️ Counting only the literal `t(` reported a fully wired file as unwired.
 * Two files here deliberately wrap the lookup rather than call it directly:
 * `checklists/ticket_view.js` has `chkT()` because its own `forEach(t => ...)`
 * loops SHADOW the global `t`, and `assets/js/calendar.js` has `tr()`. Both are
 * the right thing to do, and to the old regex both looked like zero t() calls.
 *
 * 🔑 A wrapper is recognised by what it DOES, not by its name: a function whose
 * body calls `window.t(`. Matching on a list of known names would have to be
 * kept in step with the code, and the whole point of this script is to find the
 * places nobody remembered to keep in step.
 */
function tCalls(string $src): int
{
    $n = preg_match_all('/(?<![A-Za-z0-9_$.])(?:window\.)?t\s*\(/', $src);

    // function NAME(...) { ... window.t( ... }  — take the first 400 chars of
    // the body, which is plenty for a lookup wrapper and stops a huge function
    // that happens to mention window.t from being counted as one.
    if (preg_match_all('/function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\([^)]*\)\s*\{(.{0,400}?)\}/s', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $fn) {
            [, $name, $body] = $fn;
            if ($name === 't' || !str_contains($body, 'window.t(')) continue;
            $n += preg_match_all('/(?<![A-Za-z0-9_$.])' . preg_quote($name, '/') . '\s*\(/', $src);
        }
    }

    return $n;
}

/**
 * Visible English strings a user would read.
 *
 * For PHP/HTML: text sitting between tags, and the value of a placeholder or
 * title attribute. For JS: quoted strings of two or more words that start with
 * a capital — a label or a sentence, not an id, class or key.
 *
 * ⚠️ Script and style blocks are stripped from HTML first, or every line of
 * inline JavaScript counts as page text.
 */
function visibleStrings(string $src, bool $isJs): int
{
    if ($isJs) {
        preg_match_all('/([\'"])([A-Z][a-z]+(?:[ ,\'-][A-Za-z]+){1,12}[.?!]?)\1/', $src, $m);
        return count(array_filter($m[2], fn($s) => !preg_match('/^[A-Z][a-z]+ ?[A-Z]/', $s) || str_contains($s, ' ')));
    }
    $src = preg_replace('~<script\b.*?</script>~is', '', $src);
    $src = preg_replace('~<style\b.*?</style>~is', '', $src);
    $n = preg_match_all('~>\s*([A-Z][A-Za-z]*(?:[ ,\'&;-][A-Za-z]+){1,15}[.?!]?)\s*<~', $src, $m);
    $n += preg_match_all('~\b(?:placeholder|title|aria-label)\s*=\s*"([A-Z][^"]{4,60})"~', $src, $m2);
    return $n;
}

// ---------------------------------------------------------------- scan

$files = [];
foreach (['php', 'js'] as $ext) {
    foreach (glob("*/*.$ext") as $f)        $files[] = $f;
    foreach (glob("*/*/*.$ext") as $f)      $files[] = $f;
    foreach (glob("*/*/*/*.$ext") as $f)    $files[] = $f;
}
sort($files);
$files = array_values(array_unique(array_filter($files, fn($f) => !skipped($f))));

$bad = [];
$warn = [];
$rows = [];

foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $isJs = str_ends_with($f, '.js');

    // A page that renders nothing is not this check's business.
    if (!$isJs && !preg_match('/<(?:html|body|div|table|form|h1|h2|button)\b/i', $src)) continue;

    $vis = visibleStrings($src, $isJs);
    if ($vis < MIN_STRINGS) continue;

    $t = tCalls($src);
    $rows[] = [$f, $vis, $t];

    if ($t === 0)                  $bad[]  = [$f, $vis, $t];
    elseif ($vis > $t * 3)         $warn[] = [$f, $vis, $t];
}

usort($rows, fn($a, $b) => $b[1] <=> $a[1]);

if ($all) {
    printf("%-52s %8s %8s\n", 'file', 'visible', 't()');
    foreach ($rows as [$f, $v, $t]) printf("%-52s %8d %8d\n", $f, $v, $t);
    echo "\n";
}

printf("scanned %d user-facing file(s)\n\n", count($rows));

if ($bad) {
    printf("🔴 %d file(s) render text and call t() ZERO times:\n", count($bad));
    foreach ($bad as [$f, $v, $t]) printf("   %-50s %4d visible string(s), 0 t()\n", $f, $v);
    echo "\n   These are shown in English in every locale, and i18n_audit.php\n";
    echo "   cannot see it: the strings are not in lang/en either.\n";
}

if ($warn) {
    printf("\n⚠️  %d file(s) look partly wired (more than 3x visible strings to t() calls).\n", count($warn));
    printf("   Reported, not failed — which strings should be translated is a judgement.\n");
    foreach (array_slice($warn, 0, 10) as [$f, $v, $t]) printf("   %-50s %4d visible, %3d t()\n", $f, $v, $t);
    if (count($warn) > 10) printf("   ... and %d more\n", count($warn) - 10);
}

if (!$bad) {
    echo "\nEvery user-facing file that renders text calls t() at least once.\n";
    exit(0);
}
exit(1);
