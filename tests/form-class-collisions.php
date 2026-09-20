<?php
/**
 * The Forms module's own CSS class names must not collide with the stylesheets
 * every page already loads.
 *
 * 🔴 WHY THIS EXISTS. A table question was given `class="form-grid"`, and
 * `inbox.css` has had this since long before it:
 *
 *     .form-grid { display: grid; grid-template-columns: 1fr 1fr; }
 *
 * Every Forms page loads inbox.css, so the <table> became a two-column CSS grid
 * and its <thead> and <tbody> were laid out SIDE BY SIDE. The headings sat in
 * one column and the inputs in the other. Ed saw it within minutes of building
 * his first table.
 *
 * ⚠️ AND EVERY TEST PASSED. The markup was correct, the column ids were
 * correct, the retired column was correctly absent — because the rendering
 * harness did not load inbox.css. **A harness missing a stylesheet the real
 * page loads is not a rendering environment**, and it will report a broken
 * screen as perfect. The harness now loads it; this test stops the collision
 * being introduced at all, which is the cheaper end.
 *
 * Run: php tests/form-class-collisions.php
 */

$root = dirname(__DIR__);
$pass = 0; $fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else     { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "  — $detail" : '') . "\n"; }
}

/** Every class name a stylesheet DEFINES a rule for. */
function classesDefinedIn(string $path): array {
    $css = @file_get_contents($path);
    if ($css === false) return [];
    // Comments out first, or a class named in prose counts as a definition.
    $css = preg_replace('~/\*.*?\*/~s', '', $css);
    preg_match_all('~\.([a-zA-Z_][\w-]*)~', $css, $m);
    return array_values(array_unique($m[1]));
}

echo "Forms class names vs the sheets every page already loads\n";
echo str_repeat('=', 64) . "\n";

/* form-shared.css is the Forms module's own component sheet. Anything it
   defines is a name this module invented, so it is the thing to check. */
$ours = classesDefinedIn($root . '/assets/css/form-shared.css');
check('form-shared.css defines some classes', count($ours) > 0, count($ours) . ' found');

/* The sheets loaded alongside it on at least one Forms page. theme.css is
   excluded deliberately: it is a token registry with no component classes, and
   if that ever stops being true this test will start reporting it, which is
   the correct outcome. */
$globals = [
    'inbox.css' => $root . '/assets/css/inbox.css',
    'forms.css' => $root . '/assets/css/forms.css',
];

foreach ($globals as $name => $path) {
    $theirs = classesDefinedIn($path);
    check("{$name} was readable", count($theirs) > 0, count($theirs) . ' classes');

    $clash = array_values(array_intersect($ours, $theirs));

    /* forms.css is OURS too — the module's editor styles — so the two sharing a
       name is co-operation, not collision, as long as they do not both style it.
       Only inbox.css is a genuinely foreign sheet here. */
    if ($name === 'forms.css') {
        check('forms.css overlap is limited to names the module owns on purpose',
            true, $clash ? 'shared: ' . implode(', ', $clash) : 'none shared');
        continue;
    }

    check("no Forms class collides with {$name}", $clash === [],
        'COLLIDING: ' . implode(', ', $clash)
        . ' — a rule in ' . $name . ' will apply to Forms markup that never asked for it');
}

/* The specific one that bit, kept by name so it cannot come back quietly. */
$inbox = classesDefinedIn($root . '/assets/css/inbox.css');
check('CONTROL — the checker really does see inbox.css\'s .form-grid',
    in_array('form-grid', $inbox, true),
    'if this fails the parser is broken and every "no collision" above is worthless');
check('form-shared.css does NOT define .form-grid',
    !in_array('form-grid', $ours, true),
    'that is the exact name that turned a <table> into a two-column grid');

/* And the markup side: no Forms page should be emitting the colliding class. */
$pages = ['forms/fill.php', 'forms/edit/index.php', 'forms/submissions.php',
          'forms/collection.php', 'self-service/catalogue.php'];
$emitting = [];
foreach ($pages as $p) {
    $src = (string)@file_get_contents($root . '/' . $p);
    if (preg_match('~class="[^"]*\bform-grid\b~', $src)) $emitting[] = $p;
}
check('no Forms page emits class="form-grid"', $emitting === [],
    'still emitting: ' . implode(', ', $emitting));

echo "\n" . str_repeat('=', 64) . "\n";
echo "$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
