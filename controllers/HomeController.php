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
            'h1'              => 'Gujarati Font Converter',
            'introText'       => 'Convert 90+ legacy (non-Unicode) Gujarati fonts such as LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti and Sulekh to Unicode (Shruti / Nirmala UI) — and back from Unicode to legacy fonts. Completely free, no software to install. Your text is never stored on the server.',
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
        $names = ['gu' => 'Gujarati', 'hi' => 'Hindi', 'mr' => 'Marathi', 'ne' => 'Nepali'];
        $paths = ['gu' => 'gujarati-font-converter', 'hi' => 'hindi-font-converter', 'mr' => 'marathi-font-converter', 'ne' => 'nepali-font-converter'];
        $native = $names[$code] ?? $lang['name'];
        View::render('home', [
            'fonts'           => $fonts,
            'popularFonts'    => array_values(array_filter($fonts, fn($f) => (int)$f['is_popular'] === 1)),
            'selectedSlug'    => $fonts[0]['font_slug'] ?? '',
            'metaTitle'       => "{$lang['name']} Font Converter — Legacy to Unicode | Free Online Tool",
            'metaDescription' => "Free online {$lang['name']} font converter. Convert legacy {$lang['name']} fonts to Unicode ({$lang['unicode_font']}) and back instantly. 100% free, no installation.",
            'canonical'       => App::url('/' . ($paths[$code] ?? '')),
            'h1'              => "{$lang['name']} Font Converter",
            'introText'       => "Convert legacy {$lang['name']} fonts to Unicode ({$lang['unicode_font']}). " . count($fonts) . " fonts available.",
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
            'metaTitle'       => "{$name} to Unicode Converter | Free Online {$font['lang_name']} Font Converter",
            'metaDescription' => "Instantly convert {$name} font to Unicode ({$font['unicode_font']}) — free, accurate and secure. {$name} to Unicode converter online, no software needed.",
            'canonical'       => App::url("/{$slug}-to-unicode-converter"),
            'h1'              => "{$name} Font to Unicode Converter",
            'introText'       => "{$name} is a popular legacy (non-Unicode) {$font['lang_name']} font. Paste your {$name} text into the box below and get Unicode ({$font['unicode_font']}) in one click.",
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
