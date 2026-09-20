<?php
/**
 * The list of permitted field widths is written down THREE times, and all three
 * must agree.
 *
 *   includes/services/forms.php   FormsService::FIELD_WIDTHS   — what may be saved
 *   assets/js/form-logic.js       WIDTHS                       — what the fillers render
 *   forms/edit/index.php          FIELD_WIDTHS                 — what the builder offers
 *
 * 🔴 WHY THIS TEST EXISTS. Three hand-maintained lists that have to agree is
 * exactly the shape that produced #121 (the index backfill list drifting from
 * freeitsm.sql, three times) and the lesson written up there: **a guard a human
 * has to remember is not a guard.**
 *
 * The failure would be quiet. Add a width to the builder and not the service,
 * and the picker offers a choice that is refused on save. Add it to the service
 * and not to form-logic, and a saved width renders as full. Neither errors.
 *
 * They are not derived from one source because the three live in three
 * languages loaded by different pages; a test is the cheaper guard.
 *
 * Run: php tests/field-widths-agree.php
 */

$root = dirname(__DIR__);
$pass = 0; $fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else     { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "  — $detail" : '') . "\n"; }
}

/** Pull a bracketed integer list out of a file by regex, or return null. */
function intList(string $path, string $pattern): ?array {
    $src = @file_get_contents($path);
    if ($src === false) return null;
    if (!preg_match($pattern, $src, $m)) return null;
    $out = array_map('intval', array_map('trim', explode(',', $m[1])));
    return $out ?: null;
}

echo "Field widths — two lists, one meaning\n";
echo str_repeat('=', 60) . "\n";

require_once $root . '/includes/services/forms.php';
$service = FormsService::FIELD_WIDTHS;

$logic = intList($root . '/assets/js/form-logic.js',  '/var\s+WIDTHS\s*=\s*\[([0-9,\s]+)\]/');

check('the service defines a width list', !empty($service));
check('form-logic.js defines one',        $logic !== null,   'pattern did not match — was the variable renamed?');

if ($logic !== null) check('form-logic agrees with the service', $service === $logic,
    'service=' . implode(',', $service) . '  logic=' . implode(',', $logic));

/* ⭐ THERE USED TO BE A THIRD LIST, in forms/edit/index.php. The builder now
   reads FormLogic's instead of declaring its own, so the drift it could suffer
   is gone rather than guarded. What has to be asserted now is that it really
   does defer — a future edit that reintroduces a literal list here would
   silently recreate the third source this test was written for. */
$builderSrc = (string)@file_get_contents($root . '/forms/edit/index.php');
check('the builder defers to FormLogic for widths',
    (bool)preg_match('/const\s+FIELD_WIDTHS\s*=\s*FormLogic\.FIELD_WIDTHS\s*;/', $builderSrc),
    'the builder should read FormLogic.FIELD_WIDTHS, not declare its own');
check('the builder declares NO literal width list of its own',
    !preg_match('/const\s+FIELD_WIDTHS\s*=\s*\[/', $builderSrc),
    'a literal list reappeared in forms/edit/index.php — that is the third source again');
check('the builder loads form-logic.js (or FormLogic is undefined at runtime)',
    strpos($builderSrc, 'assets/js/form-logic.js') !== false,
    'the builder reads FormLogic but never loads it');

/* The default must be on the list, and it must be the widest — a default that
   is not full width would silently re-lay out every existing form, since every
   pre-existing field has no width at all. */
check('the default is one of the permitted widths',
    in_array(FormsService::FIELD_WIDTH_DEFAULT, $service, true));
check('the default is the FULL row (absent must mean unchanged)',
    FormsService::FIELD_WIDTH_DEFAULT === max($service),
    'default=' . FormsService::FIELD_WIDTH_DEFAULT . ' max=' . max($service));

/* Every width must have a PARTNER that completes the row — 9 goes with 3,
   8 with 4, 6 with 6. Otherwise a width can be chosen that can never sit
   beside anything, which is a picker offering a choice with no purpose.
   ⚠️ NOT "12 is divisible by w": 9 and 8 are on the list precisely because
   the asymmetric pairs are what a real document wants (Area | Date is 8+4,
   not 6+6), and neither divides 12. That was this test's own first, wrong
   assumption. */
foreach ($service as $w) {
    check("width $w is in range", $w > 0 && $w <= 12);
    if ($w === 12) continue;                       // the whole row needs no partner
    check("width $w has a partner (" . (12 - $w) . ") that completes the row",
        in_array(12 - $w, $service, true));
}

/* CONTROL — the checker must be able to FAIL. A test that only ever passes is
   not evidence, and this one is entirely pattern-matching. */
$fakeOk = ([12, 6] === [12, 6]);
$fakeBad = ([12, 6] === [12, 5]);
check('CONTROL — identical lists compare equal', $fakeOk);
check('CONTROL — differing lists do NOT compare equal', !$fakeBad);

echo str_repeat('=', 60) . "\n";
echo "$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
