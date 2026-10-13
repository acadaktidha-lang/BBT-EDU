<?php
/**
 * CLI checks for the parser, selector and renderer against real Trustindex
 * output saved in tests/fixtures (two different widget layouts).
 *
 *   php tests/run.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/Parser.php';
require __DIR__ . '/../src/Selector.php';
require __DIR__ . '/../src/Renderer.php';

use BBTGoogleReviews\Parser;
use BBTGoogleReviews\Renderer;
use BBTGoogleReviews\Selector;

$fail = 0;
function check(string $what, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    $fail += $ok ? 0 : 1;
}

foreach (['layout-grid.html', 'layout-slider.html'] as $f) {
    $r = Parser::parse(file_get_contents(__DIR__ . '/fixtures/' . $f));
    check("$f: 6 reviews", count($r) === 6);
    check("$f: every review has a name", count(array_filter($r, fn($x) => $x['name'] !== '')) === 6);
    check("$f: all five stars", array_unique(array_column($r, 'stars')) === [5.0]);
    check("$f: text read through R-CONTENT", str_contains(implode(' ', array_column($r, 'text')), 'I enrolled in cybersecurity'));
    check("$f: photos are Google avatar URLs", count(array_filter($r, fn($x) => str_contains($x['photo'], 'googleusercontent.com'))) === 6);
}

$reviews = Parser::parse(file_get_contents(__DIR__ . '/fixtures/layout-grid.html'));
$photoOf = array_column($reviews, 'photo', 'name');
$hasPhoto = array_fill_keys(array_column($reviews, 'photo'), false);
$hasPhoto[$photoOf['Asad Subhani']] = true;
$rules = ['min_stars' => 4.0, 'photo' => 'only', 'exclude' => ['Mr Chicken'], 'max' => 8];

$only = Selector::select($reviews, $hasPhoto, $rules);
check("photo=only keeps just Asad", array_column($only, 'name') === ['Asad Subhani']);

$prefer = Selector::select($reviews, $hasPhoto, ['photo' => 'prefer'] + $rules);
check("photo=prefer puts Asad first", ($prefer[0]['name'] ?? '') === 'Asad Subhani');
check("block list drops Mr Chicken", !in_array('Mr Chicken', array_column($prefer, 'name'), true));
check("prefer keeps the other five", count($prefer) === 5);
check("max caps the list", count(Selector::select($reviews, $hasPhoto, ['photo' => 'any', 'max' => 2] + $rules)) === 2);

$html = Renderer::cards($prefer);
check("renders one figure per review", substr_count($html, '<figure class="x-rv">') === 5);
check("real photo rendered as img, sized", str_contains($html, '=s96-c"') && substr_count($html, '<img class="x-rv-av"') === 1);
check("others get initials", str_contains($html, '>EA</span>') && str_contains($html, '>MB</span>'));
check("long review folds", str_contains($html, 'x-rv-text is-long') && str_contains($html, 'Read more'));
check("text is escaped", !str_contains(Renderer::card(['name' => '<b>x</b>', 'stars' => 5.0, 'photo' => '', 'text' => '<script>', 'real_photo' => false]), '<script>'));

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit($fail ? 1 : 0);
