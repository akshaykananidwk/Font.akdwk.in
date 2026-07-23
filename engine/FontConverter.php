<?php
/**
 * મુખ્ય કન્વર્ઝન એન્જિન — Legacy ⇄ Unicode.
 *
 * અલ્ગોરિધમ (Legacy → Unicode):
 *  1. pre_process rules
 *  2. strtr() વડે longest-match-first replacement
 *     (PHP નું strtr() array સાથે હંમેશા સૌથી લાંબી key પહેલા match કરે છે,
 *      અને replace થયેલો ભાગ ફરી search થતો નથી — બરાબર જે જોઈએ છે.)
 *  3. Reorder:
 *     a. િ માત્રા: legacy માં cluster પહેલાં ટાઇપ થાય છે → Unicode માં cluster પછી
 *     b. રેફ (ર્): legacy માં syllable પછી ટાઇપ થાય છે → Unicode માં cluster પહેલાં
 *     (બન્ને Private-Use markers થી થાય છે — ambiguity શૂન્ય)
 *  4. post_process rules
 *  5. NFC normalization (intl હોય તો)
 *
 * Unicode → Legacy: બરાબર ઊલટું — પહેલા reph પાછળ ખસે, પછી િ આગળ, પછી reverse map.
 */

defined('BASE_PATH') or die('Direct access denied');

class FontConverter
{
    /** ગુજરાતી cluster regex ટુકડાઓ (વ્યંજન + નુક્તા + હલંત ચેઇન) */
    private const CONSONANT = '[\x{0A95}-\x{0AB9}]\x{0ABC}?';
    private const CLUSTER   = '(?:[\x{0A95}-\x{0AB9}]\x{0ABC}?(?:\x{0ACD}[\x{0A95}-\x{0AB9}]\x{0ABC}?)*)';
    private const MATRAS    = '[\x{0ABE}-\x{0ACC}]*';   // ા થી ૌ (હલંત 0ACD બહાર છે)
    private const SIGNS     = '[\x{0A81}\x{0A82}\x{0A83}]?'; // ઁ ં ઃ

    /** આનાથી મોટા input માટે chunked processing (bytes) */
    private const CHUNK_THRESHOLD = 262144; // 256 KB
    private const CHUNK_SIZE      = 131072; // 128 KB

    private array $mapping;

    /**
     * @param string $mappingFile 'gujarati/lmg.json' જેવો relative path
     */
    public function __construct(string $mappingFile)
    {
        $this->mapping = MappingLoader::load($mappingFile);
    }

    /** Mapping stub છે (હજી data ભરાયો નથી)? */
    public function isStub(): bool
    {
        return !empty($this->mapping['is_stub']);
    }

    /** Font display name. */
    public function fontName(): string
    {
        return $this->mapping['font_name'];
    }

    /**
     * મુખ્ય entry point.
     *
     * @param string $text      input text
     * @param string $direction 'legacy_to_unicode' | 'unicode_to_legacy'
     * @param array  $options   ['preserve_html' => bool]
     * @return string converted text
     */
    public function convert(string $text, string $direction, array $options = []): string
    {
        if ($text === '' || trim($text) === '') {
            return $text; // ખાલી/માત્ર spaces — જેમનું તેમ
        }
        if ($this->isStub()) {
            throw new RuntimeException(
                'Mapping data for "' . $this->mapping['font_name'] . '" is not yet available. '
                . 'Please add mappings via Admin → Mapping Editor.'
            );
        }
        if (!in_array($direction, ['legacy_to_unicode', 'unicode_to_legacy'], true)) {
            throw new InvalidArgumentException('Invalid direction: ' . $direction);
        }

        // HTML tags જાળવવા હોય તો tags અલગ કરીને ફક્ત text nodes convert કરો
        if (!empty($options['preserve_html']) && str_contains($text, '<')) {
            $parts = preg_split('/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $out = '';
            foreach ($parts as $part) {
                if ($part !== '' && $part[0] === '<') {
                    $out .= $part; // tag — અકબંધ
                } else {
                    $out .= $this->convertChunked($part, $direction);
                }
            }
            return $out;
        }

        return $this->convertChunked($text, $direction);
    }

    /**
     * મોટા input ને whitespace boundary પર chunks માં તોડીને process કરો.
     * (Multi-char sequences ક્યારેય split ન થાય એ માટે newline/space પર જ કપાય.)
     */
    private function convertChunked(string $text, string $direction): string
    {
        if (strlen($text) <= self::CHUNK_THRESHOLD) {
            return $this->convertRaw($text, $direction);
        }
        $out = '';
        $offset = 0;
        $len = strlen($text);
        while ($offset < $len) {
            $end = min($offset + self::CHUNK_SIZE, $len);
            if ($end < $len) {
                // પાછળથી નજીકનું whitespace શોધો — sequence ન તૂટે
                $ws = strrpos(substr($text, $offset, $end - $offset), "\n");
                if ($ws === false) {
                    $ws = strrpos(substr($text, $offset, $end - $offset), ' ');
                }
                if ($ws !== false && $ws > 0) {
                    $end = $offset + $ws + 1;
                }
            }
            $out .= $this->convertRaw(substr($text, $offset, $end - $offset), $direction);
            $offset = $end;
        }
        return $out;
    }

    /**
     * એક chunk નું ખરેખરું conversion.
     */
    private function convertRaw(string $text, string $direction): string
    {
        return $direction === 'legacy_to_unicode'
            ? $this->legacyToUnicode($text)
            : $this->unicodeToLegacy($text);
    }

    /**
     * Legacy → Unicode.
     */
    private function legacyToUnicode(string $text): string
    {
        // 1. Pre-process
        $text = $this->applyRules($text, $this->mapping['pre_process']);

        // 2. Longest-match-first replacement (strtr → C-speed, longest byte-key પહેલા)
        $text = strtr($text, $this->mapping['forward_map']);

        // 3a. િ માત્રા reorder: marker + cluster → cluster + િ
        $text = preg_replace(
            '/' . preg_quote(ENGINE_IMATRA_MARKER, '/') . '(' . self::CLUSTER . ')/u',
            '$1િ',
            $text
        );
        // Orphan marker (પછી cluster ન હોય તો) → સાદી િ
        $text = str_replace(ENGINE_IMATRA_MARKER, 'િ', $text);

        // 3b. રેફ reorder: (cluster + માત્રાઓ + ચિહ્નો) + marker → ર્ + syllable
        $text = preg_replace(
            '/(' . self::CLUSTER . self::MATRAS . self::SIGNS . ')' . preg_quote(ENGINE_REPH_MARKER, '/') . '/u',
            'ર્$1',
            $text
        );
        // Orphan reph marker → ર્
        $text = str_replace(ENGINE_REPH_MARKER, 'ર્', $text);

        // 4. Post-process
        $text = $this->applyRules($text, $this->mapping['post_process']);

        // 5. NFC normalization (intl extension હોય તો)
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        return $text;
    }

    /**
     * Unicode → Legacy (ઊલટી પ્રક્રિયા).
     */
    private function unicodeToLegacy(string $text): string
    {
        // 0. NFC normalize input (decomposed input પણ handle થાય)
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        // 1. Reverse pre-process
        $text = $this->applyRules($text, $this->mapping['reverse_pre_process']);

        // 2a. રેફ પહેલા: ર્ + syllable → syllable + marker
        //     (?<!્) — આગળ હલંત હોય તો એ ્ર (રકાર) છે, રેફ નહીં
        $text = preg_replace(
            '/(?<!\x{0ACD})ર\x{0ACD}(' . self::CLUSTER . self::MATRAS . self::SIGNS . ')/u',
            '$1' . ENGINE_REPH_MARKER,
            $text
        );

        // 2b. િ માત્રા: cluster + િ → marker + cluster
        $text = preg_replace(
            '/(' . self::CLUSTER . ')િ/u',
            ENGINE_IMATRA_MARKER . '$1',
            $text
        );

        // 3. Reverse map — markers પણ map માં છે (marker → legacy glyph)
        $reverseMap = $this->mapping['reverse_map'];
        // િ / ર્ ના direct entries marker તરીકે already હોય છે; loose િ (marker વગરની,
        // દા.ત. કોઈ regex edge) માટે fallback: marker ની value વાપરો
        if (isset($reverseMap[ENGINE_IMATRA_MARKER]) && !isset($reverseMap['િ'])) {
            $reverseMap['િ'] = $reverseMap[ENGINE_IMATRA_MARKER];
        }
        $text = strtr($text, $reverseMap);

        // 4. Reverse post-process
        $text = $this->applyRules($text, $this->mapping['reverse_post_process']);

        return $text;
    }

    /**
     * pre/post process rules લાગુ કરો.
     *
     * @param array $rules normalizeRules() માંથી આવેલી validated rules
     */
    private function applyRules(string $text, array $rules): string
    {
        foreach ($rules as $rule) {
            if ($rule['regex']) {
                $result = preg_replace('/' . str_replace('/', '\/', $rule['find']) . '/u', $rule['replace'], $text);
                if ($result !== null) {
                    $text = $result;
                }
            } else {
                $text = str_replace($rule['find'], $rule['replace'], $text);
            }
        }
        return $text;
    }

    /**
     * Static convenience — font slug થી convert (DB lookup સાથે).
     *
     * @return array{converted_text: string, char_count: int, font: string, processing_time_ms: float}
     */
    public static function convertBySlug(string $fontSlug, string $text, string $direction, array $options = []): array
    {
        $db = Database::getInstance();
        $table = $db->table('fonts');
        $font = $db->fetch(
            "SELECT * FROM `{$table}` WHERE font_slug = ? AND is_active = 1 LIMIT 1",
            [$fontSlug]
        );
        if ($font === null) {
            throw new RuntimeException('Font not found: ' . $fontSlug);
        }

        $start = microtime(true);
        $converter = new self($font['mapping_file']);
        $converted = $converter->convert($text, $direction, $options);
        $timeMs = round((microtime(true) - $start) * 1000, 2);

        // Conversion counter વધારો (text ક્યારેય store થતો નથી)
        try {
            $db->query("UPDATE `{$table}` SET conversion_count = conversion_count + 1 WHERE id = ?", [$font['id']]);
        } catch (Throwable $e) {
            Logger::error('Conversion count update failed: ' . $e->getMessage());
        }

        return [
            'converted_text'     => $converted,
            'char_count'         => Helper::charCount($text),
            'font'               => $font['font_name'],
            'font_id'            => (int)$font['id'],
            'direction'          => $direction,
            'processing_time_ms' => $timeMs,
        ];
    }
}
