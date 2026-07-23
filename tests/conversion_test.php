<?php
/**
 * Conversion engine test suite — વિભાગ 4.3 ના બધા ટેસ્ટ કેસ.
 *
 * Run: php tests/conversion_test.php
 * DB ની જરૂર નથી — engine directly test થાય છે.
 *
 * નોંધ: legacy input strings LMG reference mapping ના glyph codes માં છે
 * (engine/mappings/gujarati/lmg.json). દા.ત. "કમલ" નું legacy સ્વરૂપ "kml" છે.
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/constants.php';
require CORE_PATH . '/Cache.php';
require CORE_PATH . '/Logger.php';
require ENGINE_PATH . '/MappingLoader.php';
require ENGINE_PATH . '/FontConverter.php';

mb_internal_encoding('UTF-8');

$converter = new FontConverter('gujarati/lmg.json');

$passed = 0;
$failed = 0;
$results = [];

/**
 * એક ટેસ્ટ ચલાવો અને પરિણામ નોંધો.
 */
function check(string $name, mixed $expected, mixed $actual): void
{
    global $passed, $failed, $results;
    $ok = $expected === $actual;
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
    $results[] = [
        'name'     => $name,
        'ok'       => $ok,
        'expected' => $expected,
        'actual'   => $actual,
    ];
    printf(
        "%s  %s\n",
        $ok ? '✓ PASS' : '✗ FAIL',
        $name
    );
    if (!$ok) {
        printf("        expected: %s\n        actual:   %s\n", var_export($expected, true), var_export($actual, true));
    }
}

$L2U = 'legacy_to_unicode';
$U2L = 'unicode_to_legacy';

echo "=== ગુજરાતી ફોન્ટ કન્વર્ટર — Engine Test Suite ===\n";
echo "Mapping: " . $converter->fontName() . "\n";
echo "PHP: " . PHP_VERSION . " | intl/Normalizer: " . (class_exists('Normalizer') ? 'yes' : 'no') . "\n\n";

// ---- 1. સાદો શબ્દ ----
check('સાદો શબ્દ: kml → કમલ', 'કમલ', $converter->convert('kml', $L2U));
check('સાદો શબ્દ reverse: કમલ → kml', 'kml', $converter->convert('કમલ', $U2L));

// ---- 2. છોટી ઈ માત્રા (pre-base િ reordering) ----
check('છોટી ઈ: ikrN → કિરણ', 'કિરણ', $converter->convert('ikrN', $L2U));
check('છોટી ઈ reverse: કિરણ → ikrN', 'ikrN', $converter->convert('કિરણ', $U2L));

// ---- 3. જોડાક્ષર (conjunct cluster + િ) ----
// દ્ય નો ligature glyph ± છે; explicit હલંત qÙy પણ માન્ય input છે — બન્ને એક જ Unicode આપે
check('જોડાક્ષર (ligature): iv±a → વિદ્યા', 'વિદ્યા', $converter->convert('iv±a', $L2U));
check('જોડાક્ષર (explicit હલંત): ivqÙya → વિદ્યા', 'વિદ્યા', $converter->convert('ivqÙya', $L2U));
check('જોડાક્ષર + રેફ: iv±aTI© → વિદ્યાર્થી', 'વિદ્યાર્થી', $converter->convert('iv±aTI©', $L2U));
check('જોડાક્ષર reverse (canonical ligature): વિદ્યાર્થી → iv±aTI©', 'iv±aTI©', $converter->convert('વિદ્યાર્થી', $U2L));

// ---- 4. રેફ (ર્) ----
check('રેફ: kay© → કાર્ય', 'કાર્ય', $converter->convert('kay©', $L2U));
check('રેફ: sUy© → સૂર્ય', 'સૂર્ય', $converter->convert('sUy©', $L2U));
check('રેફ: Fm© → ધર્મ', 'ધર્મ', $converter->convert('Fm©', $L2U));
check('રેફ reverse: કાર્ય → kay©', 'kay©', $converter->convert('કાર્ય', $U2L));
check('રેફ + માત્રા + અનુસ્વાર: kayŠ© → કાર્યં', 'કાર્યં', $converter->convert('kayŠ©', $L2U));
check('રેફ મધ્ય-શબ્દ: sÙvg© → સ્વર્ગ', 'સ્વર્ગ', $converter->convert('sÙvg©', $L2U));
check('રકાર (્ર) રેફ નથી: pÙrkax → પ્રકાશ', 'પ્રકાશ', $converter->convert('pÙrkax', $L2U));
check('રકાર reverse: પ્રકાશ → pÙrkax', 'pÙrkax', $converter->convert('પ્રકાશ', $U2L));

// ---- 5. ક્ષ / જ્ઞ / ત્ર / શ્ર ligature glyphs ----
check('ક્ષ: ûma → ક્ષમા', 'ક્ષમા', $converter->convert('ûma', $L2U));
check('જ્ઞ: ¿an → જ્ઞાન', 'જ્ઞાન', $converter->convert('¿an', $L2U));
check('ત્ર: XN → ત્રણ', 'ત્રણ', $converter->convert('XN', $L2U));
check('શ્ર: ®I → શ્રી', 'શ્રી', $converter->convert('®I', $L2U));
check('ક્ષ reverse (longest match): ક્ષમા → ûma', 'ûma', $converter->convert('ક્ષમા', $U2L));
check('જ્ઞ reverse: જ્ઞાન → ¿an', '¿an', $converter->convert('જ્ઞાન', $U2L));

// ---- 6. અનુસ્વાર ----
check('અનુસ્વાર: AŠk → અંક', 'અંક', $converter->convert('AŠk', $L2U));
check('અનુસ્વાર: sŠsÙkRt → સંસ્કૃત', 'સંસ્કૃત', $converter->convert('sŠsÙkRt', $L2U));
check('અનુસ્વાર reverse: સંસ્કૃત → sŠsÙkRt', 'sŠsÙkRt', $converter->convert('સંસ્કૃત', $U2L));
check('ચંદ્રબિંદુ: hªa → હઁા', 'હઁા', $converter->convert('hªa', $L2U));

// ---- 7. નુક્તા ----
check('નુક્તા: z¼ → ઝ઼', 'ઝ઼', $converter->convert('z¼', $L2U));
check('નુક્તા: f¼ → ફ઼', 'ફ઼', $converter->convert('f¼', $L2U));
check('નુક્તા reverse: ઝ઼ → z¼', 'z¼', $converter->convert('ઝ઼', $U2L));

// ---- 8. અંક ----
check('અંક: 0123456789 → ૦૧૨૩૪૫૬૭૮૯', '૦૧૨૩૪૫૬૭૮૯', $converter->convert('0123456789', $L2U));
check('અંક reverse: ૦૧૨૩૪૫૬૭૮૯ → 0123456789', '0123456789', $converter->convert('૦૧૨૩૪૫૬૭૮૯', $U2L));

// ---- 9. વિરામચિહ્ન (બદલાવા ન જોઈએ) ----
check('વિરામચિહ્ન: દંડ । ॥ યથાવત્', '। ॥', $converter->convert('। ॥', $L2U));
check('વિરામચિહ્ન: , . ? ! યથાવત્', ', . ? !', $converter->convert(', . ? !', $L2U));
check('વિરામચિહ્ન reverse: , . ? ! યથાવત્', ', . ? !', $converter->convert(', . ? !', $U2L));

// ---- 10. મિશ્ર ટેક્સ્ટ (Unicode→Legacy માં English અકબંધ) ----
check(
    'મિશ્ર: Hello ગુજરાતી 123 → Hello gujratI 123',
    'Hello gujratI 123',
    $converter->convert('Hello ગુજરાતી 123', $U2L)
);
check(
    'મિશ્ર: emoji + Devanagari દંડ યથાવત્ (U2L)',
    'nmsÙte 🙏 ।',
    $converter->convert('નમસ્તે 🙏 ।', $U2L)
);

// ---- 11. Round-trip: Legacy → Unicode → Legacy = 100% original ----
$roundTripInputs = [
    'kml',
    'ikrN',
    'iv±aTI©',
    'kay© sUy© Fm©',
    'ûma ¿an XN ®I',
    'AŠk sŠsÙkRt',
    'z¼ f¼',
    '0123456789',
    'gujratI Basana smacar, Ðje ivxeS.',
    'pÙrkax ane sÙvg©ma< …' ,
];
foreach ($roundTripInputs as $i => $input) {
    $unicode = $converter->convert($input, $L2U);
    $back = $converter->convert($unicode, $U2L);
    check('Round-trip #' . ($i + 1) . ': ' . mb_substr($input, 0, 24), $input, $back);
}

// Round-trip બીજી દિશા: Unicode → Legacy → Unicode
$unicodeInputs = ['કમલ', 'કિરણ', 'વિદ્યાર્થી', 'કાર્ય સૂર્ય ધર્મ', 'ક્ષમા જ્ઞાન ત્રણ શ્રી', 'સંસ્કૃત', 'પ્રકાશ', 'કીર્તિ'];
foreach ($unicodeInputs as $i => $input) {
    $legacy = $converter->convert($input, $U2L);
    $back = $converter->convert($legacy, $L2U);
    check('Round-trip U→L→U #' . ($i + 1) . ': ' . $input, $input, $back);
}

// ---- 12. ખાલી input ----
check('ખાલી input → ખાલી output', '', $converter->convert('', $L2U));
check('ખાલી input reverse', '', $converter->convert('', $U2L));

// ---- 13. ફક્ત spaces ----
check('ફક્ત spaces → જેમનું તેમ', '   ', $converter->convert('   ', $L2U));

// ---- 14. લાંબો ટેક્સ્ટ — performance ----
$sentence = 'gujratI Bahsana smacar iv±aTI© kay© ûma ¿an sŠsÙkRt 0123456789. ';
$long50k = str_repeat($sentence, (int)ceil(50000 / mb_strlen($sentence)));
$long50k = mb_substr($long50k, 0, 50000);
$t0 = microtime(true);
$out50k = $converter->convert($long50k, $L2U);
$elapsed50k = microtime(true) - $t0;
check('50,000 અક્ષર < 2 સેકન્ડ (લીધો: ' . round($elapsed50k, 3) . 's)', true, $elapsed50k < 2.0);
check('50,000 અક્ષર output ખાલી નથી', true, mb_strlen($out50k) > 40000);

$long10k = mb_substr($long50k, 0, 10000);
$t0 = microtime(true);
$converter->convert($long10k, $L2U);
$elapsed10k = microtime(true) - $t0;
check('10,000 અક્ષર < 300ms (લીધો: ' . round($elapsed10k * 1000, 1) . 'ms)', true, $elapsed10k < 0.3);

// Round-trip on long text
$t0 = microtime(true);
$back50k = $converter->convert($out50k, $U2L);
check('50k round-trip 100% સચોટ', $long50k, $back50k);

// ---- 15. Emoji + Unicode ----
check('Emoji: 🙏 nmsÙte → 🙏 નમસ્તે', '🙏 નમસ્તે', $converter->convert('🙏 nmsÙte', $L2U));

// ---- 16. HTML tags (preserve_html option) ----
check(
    'HTML tags: <b>kml</b> → <b>કમલ</b>',
    '<b>કમલ</b>',
    $converter->convert('<b>kml</b>', $L2U, ['preserve_html' => true])
);
check(
    'HTML tags reverse: <b>કમલ</b> → <b>kml</b>',
    '<b>kml</b>',
    $converter->convert('<b>કમલ</b>', $U2L, ['preserve_html' => true])
);
check(
    'HTML attribute માં glyph chars ન કન્વર્ટ થાય',
    '<span class="bold">કમલ</span>',
    $converter->convert('<span class="bold">kml</span>', $L2U, ['preserve_html' => true])
);

// ---- 17. Composed matra combos (ા+ે = ો post_process) ----
check('Composed ો: kae → કો', 'કો', $converter->convert('kae', $L2U));
check('Composed alternate: Aae → ઓ', 'ઓ', $converter->convert('Aae', $L2U));
check('Alternate normalize: Aa → આ → canonical Ð', 'Ð', $converter->convert($converter->convert('Aa', $L2U), $U2L));

// ---- 18. Stub mapping ભૂલ-સંદેશ ----
try {
    $stub = new FontConverter('gujarati/saral.json');
    $stub->convert('abc', $L2U);
    check('Stub mapping RuntimeException આપે', true, false);
} catch (RuntimeException $e) {
    check('Stub mapping RuntimeException આપે', true, str_contains($e->getMessage(), 'not yet available'));
}

// ---- 19. Invalid direction ----
try {
    $converter->convert('abc', 'sideways');
    check('Invalid direction exception', true, false);
} catch (InvalidArgumentException $e) {
    check('Invalid direction exception', true, true);
}

// ---- સારાંશ ----
echo "\n=== પરિણામ ===\n";
printf("કુલ: %d | પાસ: %d | ફેલ: %d\n", $passed + $failed, $passed, $failed);
if ($failed > 0) {
    echo "\nફેલ થયેલા ટેસ્ટ:\n";
    foreach ($results as $r) {
        if (!$r['ok']) {
            echo "  ✗ {$r['name']}\n";
        }
    }
    exit(1);
}
echo "બધા ટેસ્ટ પાસ ✓\n";
exit(0);
