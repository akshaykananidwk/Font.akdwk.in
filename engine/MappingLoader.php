<?php
/**
 * Mapping ફાઇલો લોડ + validate + compile + cache.
 *
 * JSON mapping ફાઇલમાંથી conversion માટે તૈયાર compiled arrays બનાવે છે:
 *  - forward_map  : legacy glyph → Unicode (strtr માટે; longest-match strtr જાતે કરે છે)
 *  - reverse_map  : Unicode → legacy glyph
 *  - pre/post process rules
 *
 * ખાસ નોંધ (engine internals):
 *  - reorder_rules.reph_chars માં આપેલા LEGACY glyphs નું output "ર્" હોય તો
 *    તેને ENGINE_REPH_MARKER થી બદલવામાં આવે છે, જેથી reordering અસ્પષ્ટ ન બને.
 *  - એ જ રીતે exact "િ" value ને ENGINE_IMATRA_MARKER થી બદલાય છે.
 *  - FontConverter reorder પછી markers ને સાચી જગ્યાએ "ર્" / "િ" બનાવે છે.
 */

defined('BASE_PATH') or die('Direct access denied');

class MappingLoader
{
    /** In-request memo — એક request માં ફરી compile ન થાય. */
    private static array $memo = [];

    /**
     * Mapping compile કરીને મેળવો (file cache + mtime validation સાથે).
     *
     * @param string $mappingFile 'gujarati/lmg.json' જેવો relative path
     * @return array compiled mapping structure
     * @throws RuntimeException ફાઇલ ન મળે/અમાન્ય હોય તો
     */
    public static function load(string $mappingFile): array
    {
        if (isset(self::$memo[$mappingFile])) {
            return self::$memo[$mappingFile];
        }

        // Path traversal સામે રક્ષણ
        if (preg_match('#\.\.|^/|\\\\#', $mappingFile)) {
            throw new RuntimeException('Invalid mapping file path');
        }
        $fullPath = MAPPINGS_PATH . '/' . $mappingFile;
        if (!is_file($fullPath)) {
            throw new RuntimeException("Mapping file not found: {$mappingFile}");
        }

        $mtime = (int)filemtime($fullPath);
        $cacheKey = 'mapping_' . str_replace(['/', '.'], '_', $mappingFile);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['_mtime'] ?? -1) === $mtime) {
            self::$memo[$mappingFile] = $cached;
            return $cached;
        }

        $compiled = self::compile($fullPath);
        $compiled['_mtime'] = $mtime;
        Cache::set($cacheKey, $compiled);
        self::$memo[$mappingFile] = $compiled;
        return $compiled;
    }

    /**
     * JSON ફાઇલ → compiled arrays.
     */
    private static function compile(string $fullPath): array
    {
        $json = file_get_contents($fullPath);
        $data = json_decode((string)$json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON in mapping file: ' . basename($fullPath) . ' — ' . json_last_error_msg());
        }

        $reorderRules = $data['reorder_rules'] ?? [];
        $rephChars = $reorderRules['reph_chars'] ?? ['ર્'];
        $iMatraChars = $reorderRules['matra_i_prefix'] ?? ['િ'];

        // ---- Forward map બનાવો (લાંબા keys પહેલા — strtr પોતે longest-first કરે છે,
        //      પણ order જાળવવી reverse map ના preference માટે જરૂરી છે) ----
        $forward = [];
        $groups = $data['mappings'] ?? [];
        // 4_char → 3_char → 2_char → 1_char ક્રમ (તથા બીજા કોઈ પણ group name)
        uksort($groups, function ($a, $b) {
            $na = (int)$a;
            $nb = (int)$b;
            return $nb <=> $na;
        });
        foreach ($groups as $pairs) {
            if (!is_array($pairs)) {
                continue;
            }
            foreach ($pairs as $legacy => $unicode) {
                $legacy = (string)$legacy;
                $unicode = (string)$unicode;
                if ($legacy === '') {
                    continue;
                }
                // Reph glyph → marker (reordering માટે)
                if (in_array($unicode, $rephChars, true)) {
                    $unicode = ENGINE_REPH_MARKER;
                }
                // Pre-base િ glyph → marker
                if (in_array($unicode, $iMatraChars, true)) {
                    $unicode = ENGINE_IMATRA_MARKER;
                }
                $forward[$legacy] = $unicode;
            }
        }

        // ---- Reverse map (flip; પહેલી entry જીતે — mapping author નો ક્રમ preference છે) ----
        $reverse = [];
        foreach ($forward as $legacy => $unicode) {
            if ($unicode === '' ) {
                continue;
            }
            // Markers reverse માં પણ marker તરીકે જ રહે (reverse reorder markers મૂકે છે)
            if (!isset($reverse[$unicode])) {
                $reverse[$unicode] = $legacy;
            }
        }
        // reverse_exceptions સૌથી ઊંચી precedence
        foreach (($data['reverse_exceptions'] ?? []) as $unicode => $legacy) {
            $reverse[(string)$unicode] = (string)$legacy;
        }

        return [
            'font_name'    => $data['font_name'] ?? basename($fullPath, '.json'),
            'language'     => $data['language'] ?? 'gujarati',
            'version'      => $data['version'] ?? '1.0',
            'direction'    => $data['direction'] ?? 'both',
            'pre_process'  => self::normalizeRules($data['pre_process'] ?? []),
            'post_process' => self::normalizeRules($data['post_process'] ?? []),
            'reverse_pre_process'  => self::normalizeRules($data['reverse_pre_process'] ?? []),
            'reverse_post_process' => self::normalizeRules($data['reverse_post_process'] ?? []),
            'forward_map'  => $forward,
            'reverse_map'  => $reverse,
            'is_stub'      => empty($forward),
        ];
    }

    /**
     * pre/post process rules validate કરો.
     *
     * @return array<int, array{find: string, replace: string, regex: bool}>
     */
    private static function normalizeRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['find'])) {
                continue;
            }
            $isRegex = !empty($rule['regex']);
            if ($isRegex) {
                // Regex validate કરો — ખરાબ pattern crash ન કરે
                if (@preg_match('/' . str_replace('/', '\/', $rule['find']) . '/u', '') === false) {
                    Logger::warn('Invalid regex in mapping rule skipped: ' . $rule['find']);
                    continue;
                }
            }
            $out[] = [
                'find'    => (string)$rule['find'],
                'replace' => (string)($rule['replace'] ?? ''),
                'regex'   => $isRegex,
            ];
        }
        return $out;
    }

    /**
     * ઉપલબ્ધ બધી mapping ફાઇલોની list (installer/admin માટે).
     *
     * @return array<int, array{file: string, language: string}>
     */
    public static function listMappingFiles(): array
    {
        $out = [];
        foreach (glob(MAPPINGS_PATH . '/*/*.json') ?: [] as $file) {
            $rel = str_replace(MAPPINGS_PATH . '/', '', $file);
            if (str_starts_with(basename($rel), '_')) {
                continue; // _template.json વગેરે skip
            }
            $out[] = [
                'file'     => $rel,
                'language' => dirname($rel),
            ];
        }
        return $out;
    }
}
