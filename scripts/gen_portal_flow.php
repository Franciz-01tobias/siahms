<?php
/* Generate the "Flow" portal background: a family of phase-shifted sine curves.
 *
 * The look Ed asked for - fine parallel lines sweeping across the page like a
 * ribbon - is one equation drawn many times with the phase walked forward a
 * little on each pass. Three terms do all the work:
 *
 *   y(x) = baseline(t) + amplitude(t) * envelope(x) * sin(2π·f·x/W + phase(t))
 *
 *   baseline(t)   walks DOWN the canvas as the curve index t goes 0 → 1, so the
 *                 family sweeps rather than sitting on top of itself.
 *   amplitude(t)  is largest in the middle of the family (sin(πt)), which is
 *                 what makes the band bulge and read as a twisting ribbon
 *                 instead of a stack of identical waves.
 *   envelope(x)   pinches the ends (sin(πx/W)) so the lines converge at the left
 *                 and right edges - that convergence is the whole effect; without
 *                 it you get wallpaper.
 *   phase(t)      shifts each successive curve, which is what makes neighbouring
 *                 lines cross and create the moiré ribbon.
 *
 * Written as TWO files rather than one with `currentColor`: an SVG referenced
 * from `background-image` is a separate document and cannot inherit the page's
 * colour, and masks/filters to work around that would be cleverness where two
 * small files will do.
 */

$W = 1600;
$H = 900;
$CURVES  = 34;     // enough to read as a mesh, few enough to stay a small file
$SAMPLES = 72;    // points per curve; beyond this the SVG grows for nothing
$FREQ    = 1.35;   // wavelengths across the width

function curves(int $W, int $H, int $CURVES, int $SAMPLES, float $FREQ): array {
    $paths = [];
    for ($i = 0; $i < $CURVES; $i++) {
        $t = $CURVES > 1 ? $i / ($CURVES - 1) : 0.0;

        $baseline  = $H * 0.30 + $t * ($H * 0.52);
        $amplitude = $H * 0.10 + sin($t * M_PI) * ($H * 0.13);
        $phase     = $t * 2.35;

        $pts = [];
        for ($s = 0; $s <= $SAMPLES; $s++) {
            $x  = $W * $s / $SAMPLES;
            $u  = $x / $W;
            // Pinch the ends so the family converges at both edges.
            $envelope = 0.25 + 0.75 * sin(M_PI * $u);
            $y = $baseline + $amplitude * $envelope * sin(2 * M_PI * $FREQ * $u + $phase);
            $pts[] = ((int)round($x)) . ',' . ((int)round($y));
        }
        // Thinner and fainter towards the back of the family, which gives the
        // band depth instead of a flat screen of identical strokes.
        $w = round(0.55 + 0.55 * sin($t * M_PI), 2);
        $o = round(0.35 + 0.65 * sin($t * M_PI), 2);
        $paths[] = ['pts' => implode(' ', $pts), 'w' => $w, 'o' => $o];
    }
    return $paths;
}

function svg(array $paths, int $W, int $H, string $stroke, float $groupOpacity): string {
    $out = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" '
         . 'width="' . $W . '" height="' . $H . '" fill="none">';
    // Presence is baked in here rather than left to CSS: `background-image` gives
    // no opacity control, and a second element just to fade one layer is more
    // machinery than a number in the file it belongs to.
    $out .= '<g stroke="' . $stroke . '" fill="none" opacity="' . $groupOpacity . '" stroke-linecap="round" stroke-linejoin="round">';
    foreach ($paths as $p) {
        $out .= '<polyline points="' . $p['pts'] . '" stroke-width="' . $p['w'] . '" opacity="' . $p['o'] . '"/>';
    }
    $out .= '</g></svg>';
    return $out;
}

$paths = curves($W, $H, $CURVES, $SAMPLES, $FREQ);

$dir = 'assets/img';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }

// Light pages get near-black strokes, dark pages near-white. The CSS then dials
// the whole thing down with its own opacity, so these are full strength here.
// A light mark on a dark ground reads fainter than the same mark inverted, so
// dark carries a little more.
foreach ([
    "$dir/portal-flow-light.svg" => ['#0b1220', 0.30],
    "$dir/portal-flow-dark.svg"  => ['#cfe3ff', 0.38],
] as $path => $spec) {
    file_put_contents($path, svg($paths, $W, $H, $spec[0], $spec[1]));
    printf("  %-38s %6.1f KB  stroke %s at %.2f
", $path, filesize($path) / 1024, $spec[0], $spec[1]);
}
echo "  {$CURVES} curves, {$SAMPLES} samples each\n";
