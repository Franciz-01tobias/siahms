<?php
/**
 * A form's LAYOUT — deriving one, and reconciling a stored one with the
 * questions that actually exist.
 *
 * 🔑 WHY THIS TEST EXISTS. Layout became its own object so that a form can be
 * re-laid-out without touching a question, and so that a cell designer can be
 * added later as a UI rather than a rewrite. Two things have to hold for that
 * to be safe, and neither would fail loudly:
 *
 *   1. A form with NO stored layout must derive exactly what it already draws.
 *      Every form that predates this stores NULL, so a wrong derivation would
 *      silently re-lay-out every existing form on every install.
 *
 *   2. A stored layout must never DROP a question. A field added or retired
 *      after the layout was saved is the case that matters: a field the layout
 *      does not mention would simply never render — the same silent-drop that
 *      an untaught field type used to cause, arriving by a different road.
 *
 * Run: php tests/form-layout.php
 */

$root = dirname(__DIR__);
require_once $root . '/includes/services/forms.php';

$pass = 0; $fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else     { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "  — $detail" : '') . "\n"; }
}

/** A field, with an optional width. Width absent means full, as it does in the database. */
function fld(int $id, ?int $width = null, string $type = 'text'): array {
    return [
        'id'          => $id,
        'field_type'  => $type,
        'label'       => 'F' . $id,
        'is_required' => 0,
        'config'      => $width === null ? null : json_encode(['width' => $width]),
    ];
}

/** Rows rendered as "8+4 | 12" so a failure reads as the shape it produced. */
function shape(array $layout): string {
    $rows = [];
    foreach ($layout['rows'] as $row) {
        $cells = [];
        foreach ($row['cells'] as $c) $cells[] = ($c['field'] ?? '-') . ':' . $c['width'];
        $rows[] = implode('+', $cells);
    }
    return implode(' | ', $rows);
}

echo "Form layout — derivation and reconciliation\n";
echo str_repeat('=', 64) . "\n";

/* ── 1. Deriving the layout a form already has ───────────────────────────── */
echo "\nDeriving from sort_order + width\n";

$d = FormsService::deriveLayout([fld(1), fld(2), fld(3)]);
check('three full-width fields make three rows', count($d['rows']) === 3, shape($d));
check('a derived layout is a flow layout', $d['type'] === 'flow', $d['type']);

$d = FormsService::deriveLayout([fld(1, 8), fld(2, 4)]);
check('8 + 4 share one row', shape($d) === '1:8+2:4', shape($d));

$d = FormsService::deriveLayout([fld(1, 6), fld(2, 6)]);
check('6 + 6 share one row', shape($d) === '1:6+2:6', shape($d));

$d = FormsService::deriveLayout([fld(1, 4), fld(2, 4), fld(3, 4)]);
check('4 + 4 + 4 share one row', shape($d) === '1:4+2:4+3:4', shape($d));

/* 8 + 6 is 14, which cannot fit — the 6 wraps, exactly as the CSS grid does. */
$d = FormsService::deriveLayout([fld(1, 8), fld(2, 6)]);
check('8 + 6 wraps rather than overflowing', shape($d) === '1:8 | 2:6', shape($d));

/* Rows are independent: halves then thirds is a real document shape. */
$d = FormsService::deriveLayout([fld(1, 6), fld(2, 6), fld(3, 4), fld(4, 4), fld(5, 4)]);
check('halves then thirds keeps rows independent', shape($d) === '1:6+2:6 | 3:4+4:4+5:4', shape($d));

/* A row that does not fill 12 is a legitimate shape — it leaves a gap, which is
   how a spacer works without any cell existing for it. */
$d = FormsService::deriveLayout([fld(1, 4), fld(2, 4), fld(3)]);
check('a part-filled row is left part-filled', shape($d) === '1:4+2:4 | 3:12', shape($d));

check('no fields makes no rows', FormsService::deriveLayout([])['rows'] === []);

/* An unknown width must fall back to full, never be trusted through. The service
   refuses bad widths on save, but a hand-edited row can still carry one. */
$d = FormsService::deriveLayout([fld(1, 7)]);
check('an impossible width falls back to full', shape($d) === '1:12', shape($d));

/* ── 2. A form with no stored layout ─────────────────────────────────────── */
echo "\nNo stored layout\n";

$fields = [fld(1, 8), fld(2, 4), fld(3)];
$derived = FormsService::deriveLayout($fields);
check('NULL derives',        FormsService::layoutFor(null, $fields) === $derived);
check('empty string derives', FormsService::layoutFor('', $fields) === $derived);
check('unreadable JSON derives rather than refusing to draw',
    FormsService::layoutFor('{not json', $fields) === $derived);
check('JSON with no rows derives',
    FormsService::layoutFor('{"type":"flow"}', $fields) === $derived);

/* ── 3. Reconciling a stored layout with the real fields ─────────────────── */
echo "\nA stored layout meets the field pool\n";

$stored = json_encode(['type' => 'flow', 'rows' => [
    ['cells' => [['field' => 2, 'width' => 6], ['field' => 1, 'width' => 6]]],
]]);

/* CONTROL FIRST: a layout that covers every field must come back EXACTLY as
   written, in its own order. Without this, "the missing field was appended"
   could just as well mean everything is always appended. */
$two = [fld(1, 6), fld(2, 6)];
$l = FormsService::layoutFor($stored, $two);
check('CONTROL — a complete layout is returned in ITS order, not the pool\'s',
    shape($l) === '2:6+1:6', shape($l));
check('CONTROL — a complete layout adds no extra rows', count($l['rows']) === 1, shape($l));

/* The case that matters: a third question added after the layout was saved. */
$three = [fld(1, 6), fld(2, 6), fld(3, 4)];
$l = FormsService::layoutFor($stored, $three);
check('a field the layout never mentioned is still rendered',
    in_array(3, FormsService::layoutFieldOrder($l), true), shape($l));
check('the unplaced field is appended, not inserted', shape($l) === '2:6+1:6 | 3:4', shape($l));

/* A question retired since the layout was saved must not leave a hole. */
$one = [fld(1, 6)];
$l = FormsService::layoutFor($stored, $one);
check('a cell pointing at a field that is gone is dropped',
    FormsService::layoutFieldOrder($l) === [1], shape($l));

/* The same field twice would render it twice and submit it twice. */
$dup = json_encode(['type' => 'flow', 'rows' => [
    ['cells' => [['field' => 1, 'width' => 6], ['field' => 1, 'width' => 6]]],
]]);
$l = FormsService::layoutFor($dup, [fld(1, 6)]);
check('a field placed twice is placed once', FormsService::layoutFieldOrder($l) === [1], shape($l));

/* ── 4. Cell values forced into range ────────────────────────────────────── */
echo "\nCells with bad values\n";

/* 🔑 A cell with no usable width falls back to the FIELD's width, never to 12 —
   otherwise a half-width field placed by the designer silently fills the row. */
$badW = json_encode(['type' => 'flow', 'rows' => [['cells' => [['field' => 1, 'width' => 99]]]]]);
$l = FormsService::layoutFor($badW, [fld(1, 6)]);
check('a bad cell width falls back to the FIELD\'s width, not to full',
    shape($l) === '1:6', shape($l));

$noW = json_encode(['type' => 'flow', 'rows' => [['cells' => [['field' => 1]]]]]);
$l = FormsService::layoutFor($noW, [fld(1, 4)]);
check('a cell with no width takes the field\'s', shape($l) === '1:4', shape($l));

$badSpan = json_encode(['type' => 'flow', 'rows' => [['cells' => [['field' => 1, 'width' => 6, 'rowspan' => 0]]]]]);
$l = FormsService::layoutFor($badSpan, [fld(1, 6)]);
check('a rowspan below 1 becomes 1', $l['rows'][0]['cells'][0]['rowspan'] === 1);

/* A cell holding no question at all is a spacer, and is what a grid layout uses
   for an empty or merged box. It must survive. */
$spacer = json_encode(['type' => 'grid', 'rows' => [
    ['cells' => [['field' => 1, 'width' => 6], ['width' => 6]]],
]]);
$l = FormsService::layoutFor($spacer, [fld(1, 6)]);
check('a cell with no question survives as a spacer', count($l['rows'][0]['cells']) === 2, shape($l));
check('a grid layout keeps its type', $l['type'] === 'grid', $l['type']);

$badType = json_encode(['type' => 'origami', 'rows' => [['cells' => [['field' => 1, 'width' => 12]]]]]);
check('an unknown layout type falls back to flow',
    FormsService::layoutFor($badType, [fld(1)])['type'] === 'flow');

/* ── 5. Flow and grid are the same format ────────────────────────────────── */
echo "\nThe shape claim the roadmap rests on\n";

/* 🔑 If these two ever stop being the same structure, adding a cell designer
   stops being a UI change and becomes a rewrite — which is the exact promise
   made to justify doing this phase first. */
$flow = FormsService::deriveLayout([fld(1, 8), fld(2, 4)]);
$asGrid = $flow; $asGrid['type'] = 'grid';
$asGrid['rows'][0]['cells'][0]['rowspan'] = 2;
$l = FormsService::layoutFor(json_encode($asGrid), [fld(1, 8), fld(2, 4)]);
check('the same rows read as a grid layout', $l['type'] === 'grid');
check('a rowspan above 1 is carried, not flattened',
    $l['rows'][0]['cells'][0]['rowspan'] === 2, 'rowspan=' . $l['rows'][0]['cells'][0]['rowspan']);
check('a flow layout is the same shape with rowspan 1',
    $flow['rows'][0]['cells'][0]['rowspan'] === 1);

/* ── 6. Carrying a layout to a new version ───────────────────────────────── */
echo "\nA new version renumbers every field\n";

/* 🔴 createVersion() copies each field to a NEW id, so a layout carried across
   verbatim points at the previous version's fields. Every cell would then be
   dropped on read and every field appended in pool order — a form that looks
   laid out while silently being in default order, indistinguishable from never
   having been designed. The same id map the conditional rules use fixes it. */
/* ⚠️ The layout deliberately places the SECOND field first. An earlier version
   of this test used pool order, and its control passed for the wrong reason:
   losing the layout falls back to the derivation, which for 8 + 4 produces the
   very same row. A layout that merely agrees with the derivation cannot show
   whether it survived. Only an order the derivation would never produce can. */
$before = json_encode(['type' => 'flow', 'rows' => [
    ['cells' => [['field' => 9, 'width' => 4], ['field' => 7, 'width' => 8]]],
]]);
$idMap  = [7 => 107, 9 => 109];

$after  = FormsService::remapLayoutFields($before, $idMap);
$l = FormsService::layoutFor($after, [fld(107, 8), fld(109, 4)]);
check('a carried layout points at the NEW version\'s fields',
    FormsService::layoutFieldOrder($l) === [109, 107], shape($l));
check('the carried layout keeps its own order, not the pool\'s',
    shape($l) === '109:4+107:8', shape($l));

/* CONTROL: without the remap the same layout must be LOST, or the two checks
   above pass whether or not remapping happened. */
$lNoRemap = FormsService::layoutFor($before, [fld(107, 8), fld(109, 4)]);
check('CONTROL — WITHOUT the remap the layout is silently lost',
    FormsService::layoutFieldOrder($lNoRemap) === [107, 109],
    'got ' . shape($lNoRemap) . ' — expected the fields back in pool order');

/* A field that did NOT come forward (retired) has no entry in the map. Keeping
   its old id means the cell is dropped on read, which is correct — inventing a
   reference would place some other question in its box. */
$withGone = json_encode(['type' => 'flow', 'rows' => [
    ['cells' => [['field' => 7, 'width' => 6], ['field' => 8, 'width' => 6]]],
]]);
$l = FormsService::layoutFor(FormsService::remapLayoutFields($withGone, [7 => 107]), [fld(107, 6)]);
check('a field left behind by the version copy drops out cleanly',
    FormsService::layoutFieldOrder($l) === [107], shape($l));

echo "\n" . str_repeat('=', 64) . "\n";
echo "$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
