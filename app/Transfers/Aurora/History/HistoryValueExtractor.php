<?php

namespace App\Transfers\Aurora\History;

class HistoryValueExtractor
{
    protected const array TIME_LABELS = ['Time', 'Czas', 'Čas'];

    protected const array USER_LABELS = ['User', 'Użytkownik', 'Užívateľ', 'Uživatel'];

    protected const array ACTION_LABELS = ['Action', 'Akcja', 'akcia', 'Akce'];

    protected const array OLD_LABELS = [
        'Old value', 'Alter Wert', 'Původní hodnota', 'Stará hodnota', 'Předchozí hodnota',
        'Előző érték', 'Stara wartość', 'Valor anterior',
    ];

    protected const array NEW_LABELS = [
        'New value', 'Neuer Wert', 'Nová hodnota', 'Új érték', 'Valor nuevo',
        'Novo valor', 'Nouvelle valeur', 'Nowa wartość', 'Nuovo valore',
    ];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function fixEncoding(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    public static function cleanArtifacts(string $html): string
    {
        $html = preg_replace('/(<\/div><\/div>)xx/', '$1', $html);
        $html = preg_replace('/(<\/td><\/tr>)xx/', '$1', $html);

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        if (str_contains($html, '&lt;') || str_contains($html, '&amp;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        }

        $html = preg_replace('/(<\/div><\/div>)xx/', '$1', $html);
        $html = preg_replace('/(<\/td><\/tr>)xx/', '$1', $html);

        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }

    public static function extractTable(string $details): array
    {
        $details = self::cleanArtifacts($details);

        $result = [
            'time'   => null,
            'user'   => null,
            'action' => null,
            'old'    => null,
            'new'    => null,
        ];

        $rows = [];

        if (preg_match_all('/<tr>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $details, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $rows[] = [$match[1], $match[2]];
            }
        }

        if (preg_match_all('/<div class="field tr">\s*<div>(.*?)<\/div>\s*<div>(.*?)<\/div>\s*<\/div>/is', $details, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $rows[] = [$match[1], $match[2]];
            }
        }

        foreach ($rows as [$label, $value]) {
            $key = self::matchLabel($label);
            if ($key === null) {
                continue;
            }

            $result[$key] = self::normalize($value);
        }

        return $result;
    }

    public static function extractPlainSentence(string $text): ?array
    {
        $text = self::cleanArtifacts($text);

        if (preg_match('/changed\s+from\s+"(.*?)"\s+to\s+"(.*?)"/is', $text, $match)) {
            return ['old' => self::normalize($match[1]), 'new' => self::normalize($match[2])];
        }

        if (preg_match('/changed\s*\((.*?)(?:→|&rarr;)(.*?)\)/is', $text, $match)) {
            return ['old' => self::normalize($match[1]), 'new' => self::normalize($match[2])];
        }

        return null;
    }

    public static function parseAdrAddress(string $html): ?array
    {
        if (!preg_match('/<div[^>]*class="[^"]*\badr\b[^"]*"[^>]*>(.*?)<\/div>\s*$/is', $html, $outer)) {
            if (!preg_match('/<div[^>]*class="[^"]*\badr\b[^"]*"[^>]*>(.*)/is', $html, $outer)) {
                return null;
            }
        }

        $block = $outer[1];

        return [
            'recipient'            => self::extractSpan($block, 'fn'),
            'organization'         => self::extractSpan($block, 'org'),
            'address_line_1'       => self::extractSpan($block, 'street-address'),
            'address_line_2'       => self::extractSpan($block, 'extended-address'),
            'locality'             => self::extractSpan($block, 'locality'),
            'postal_code'          => self::extractSpan($block, 'postal-code'),
            'administrative_area'  => self::extractSpan($block, 'administrative-area'),
            'country'              => self::extractSpan($block, 'country-name'),
        ];
    }

    public static function redactCredential(string $indirectObject, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!preg_match('/password|pin|hash/i', $indirectObject)) {
            return $value;
        }

        if ($value === '' || preg_match('/^\**$/', $value)) {
            return $value;
        }

        return '[redacted]';
    }

    protected static function extractSpan(string $block, string $class): ?string
    {
        if (!preg_match('/<span[^>]*class="[^"]*\b'.preg_quote($class, '/').'\b[^"]*"[^>]*>(.*?)<\/span>/is', $block, $match)) {
            return null;
        }

        return self::normalize(trim(strip_tags($match[1])));
    }

    protected static function matchLabel(string $label): ?string
    {
        $normalized = self::normalizeLabel($label);

        foreach (self::TIME_LABELS as $candidate) {
            if ($normalized === self::normalizeLabel($candidate)) {
                return 'time';
            }
        }

        foreach (self::USER_LABELS as $candidate) {
            if ($normalized === self::normalizeLabel($candidate)) {
                return 'user';
            }
        }

        foreach (self::ACTION_LABELS as $candidate) {
            if ($normalized === self::normalizeLabel($candidate)) {
                return 'action';
            }
        }

        foreach (self::OLD_LABELS as $candidate) {
            if ($normalized === self::normalizeLabel($candidate)) {
                return 'old';
            }
        }

        foreach (self::NEW_LABELS as $candidate) {
            if ($normalized === self::normalizeLabel($candidate)) {
                return 'new';
            }
        }

        return null;
    }

    protected static function normalizeLabel(string $label): string
    {
        $label = strip_tags($label);
        $label = trim($label);
        $label = rtrim($label, ':');
        $label = trim($label);
        $label = self::fixEncoding($label);
        $label = mb_strtolower($label);
        $label = preg_replace('/[^a-z ]+/u', '', $label);
        $label = preg_replace('/\s+/', ' ', $label);

        return trim($label);
    }
}
