<?php
/**
 * Home (converter) + language landings + 94 font-wise SEO pages.
 */

defined('BASE_PATH') or die('Direct access denied');

class HomeController
{
    /**
     * GET / — મુખ્ય કન્વર્ટર પેજ.
     */
    public static function index(): void
    {
        $fonts = self::activeFonts();
        View::render('home', [
            'fonts'           => $fonts,
            'popularFonts'    => array_values(array_filter($fonts, fn($f) => (int)$f['is_popular'] === 1)),
            'selectedSlug'    => 'lmg',
            'metaTitle'       => App::setting('default_meta_title', 'Gujarati Font Converter'),
            'metaDescription' => App::setting('default_meta_description', ''),
            'canonical'       => App::url('/'),
            'h1'              => 'ગુજરાતી ફોન્ટ કન્વર્ટર',
            'introText'       => 'LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti, Sulekh જેવા 90+ જૂના (non-Unicode) ગુજરાતી ફોન્ટને Unicode (Shruti / Nirmala UI) માં અને Unicode માંથી પાછા legacy ફોન્ટમાં કન્વર્ટ કરો — સંપૂર્ણ મફત, કોઈ સોફ્ટવેર install કર્યા વગર. તમારો ટેક્સ્ટ સર્વર પર ક્યારેય સ્ટોર થતો નથી.',
            'showFaq'         => true,
        ]);
    }

    /**
     * GET /gujarati-font-converter વગેરે — ભાષા-વાર landing.
     */
    public static function language(string $code): void
    {
        $db = Database::getInstance();
        $langTable = $db->table('languages');
        $lang = $db->fetch("SELECT * FROM `{$langTable}` WHERE code = ? AND is_active = 1", [$code]);
        if ($lang === null) {
            http_response_code(404);
            View::render('errors/404', ['metaTitle' => '404']);
            return;
        }
        $fonts = self::activeFonts((int)$lang['id']);
        $names = ['gu' => 'ગુજરાતી', 'hi' => 'हिन्दी', 'mr' => 'मराठी', 'ne' => 'नेपाली'];
        $paths = ['gu' => 'gujarati-font-converter', 'hi' => 'hindi-font-converter', 'mr' => 'marathi-font-converter', 'ne' => 'nepali-font-converter'];
        $native = $names[$code] ?? $lang['name'];
        View::render('home', [
            'fonts'           => $fonts,
            'popularFonts'    => array_values(array_filter($fonts, fn($f) => (int)$f['is_popular'] === 1)),
            'selectedSlug'    => $fonts[0]['font_slug'] ?? '',
            'metaTitle'       => "{$lang['name']} Font Converter | {$native} ફોન્ટ કન્વર્ટર — Legacy to Unicode",
            'metaDescription' => "Free online {$lang['name']} font converter. Convert legacy {$lang['name']} fonts to Unicode ({$lang['unicode_font']}) and back instantly. 100% free, no installation.",
            'canonical'       => App::url('/' . ($paths[$code] ?? '')),
            'h1'              => "{$native} ફોન્ટ કન્વર્ટર",
            'introText'       => "{$lang['name']} ભાષાના legacy ફોન્ટને Unicode ({$lang['unicode_font']}) માં કન્વર્ટ કરો. " . count($fonts) . " ફોન્ટ ઉપલબ્ધ.",
            'showFaq'         => true,
        ]);
    }

    /**
     * GET /{slug}-to-unicode-converter — 94 ફોન્ટ-વાર SEO pages.
     */
    public static function fontPage(string $slug): void
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $db = Database::getInstance();
        $fontsTable = $db->table('fonts');
        $font = $db->fetch(
            "SELECT f.*, l.name AS lang_name, l.code AS lang_code, l.unicode_font
             FROM `{$fontsTable}` f
             JOIN `" . $db->table('languages') . "` l ON l.id = f.language_id
             WHERE f.font_slug = ? AND f.is_active = 1 LIMIT 1",
            [$slug]
        );
        if ($font === null) {
            http_response_code(404);
            View::render('errors/404', ['metaTitle' => '404']);
            return;
        }

        $fonts = self::activeFonts((int)$font['language_id']);
        // સંબંધિત ફોન્ટ — એ જ ભાષાના બીજા 6
        $related = array_values(array_filter($fonts, fn($f) => $f['font_slug'] !== $slug));
        shuffle($related);
        $related = array_slice($related, 0, 6);

        $name = $font['font_name'];
        View::render('home', [
            'fonts'           => $fonts,
            'popularFonts'    => array_values(array_filter($fonts, fn($f) => (int)$f['is_popular'] === 1)),
            'selectedSlug'    => $slug,
            'metaTitle'       => "{$name} થી Unicode કન્વર્ટર | મફત ઓનલાઇન {$font['lang_name']} ફોન્ટ કન્વર્ટર",
            'metaDescription' => "{$name} ફોન્ટને Unicode ({$font['unicode_font']}) માં તરત કન્વર્ટ કરો — મફત, સચોટ અને સુરક્ષિત. {$name} to Unicode converter online, no software needed.",
            'canonical'       => App::url("/{$slug}-to-unicode-converter"),
            'h1'              => "{$name} ફોન્ટ થી Unicode કન્વર્ટર",
            'introText'       => "{$name} એ {$font['lang_name']} ભાષાનો લોકપ્રિય legacy (non-Unicode) ફોન્ટ છે. નીચેના બોક્સમાં {$name} નો ટેક્સ્ટ paste કરો અને એક ક્લિકમાં Unicode ({$font['unicode_font']}) માં મેળવો.",
            'fontPageData'    => $font,
            'relatedFonts'    => $related,
            'showFaq'         => true,
        ]);
    }

    /**
     * Active fonts list (cache સાથે).
     */
    private static function activeFonts(?int $languageId = null): array
    {
        try {
            $db = Database::getInstance();
            $table = $db->table('fonts');
            if ($languageId !== null) {
                return $db->fetchAll(
                    "SELECT * FROM `{$table}` WHERE is_active = 1 AND language_id = ? ORDER BY is_popular DESC, sort_order ASC",
                    [$languageId]
                );
            }
            return $db->fetchAll(
                "SELECT * FROM `{$table}` WHERE is_active = 1 ORDER BY is_popular DESC, sort_order ASC"
            );
        } catch (Throwable $e) {
            Logger::error('Font list failed: ' . $e->getMessage());
            return [];
        }
    }
}
