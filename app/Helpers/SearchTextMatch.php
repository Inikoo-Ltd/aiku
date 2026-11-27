<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 09:21
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Helpers;

class SearchTextMatch
{
    public const int SNIPPET_CONTEXT_CHARACTERS = 40;

    public const int MAX_SNIPPET_FRAGMENTS = 2;

    /**
     * @return array<int, string>
     */
    public static function words(string $query): array
    {
        return array_values(array_unique(array_map(
            'mb_strtolower',
            preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }

    /**
     * A word counts as found when a word of the text starts with it, as Typesense prefix search
     * does: "lamp" is found in "Lamps".
     *
     * @param array<int, string> $words
     */
    public static function containsAllWords(string $text, array $words): bool
    {
        if (!$words) {
            return false;
        }

        foreach ($words as $word) {
            if (!preg_match(self::wordPattern($word), $text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Short pieces of the plain text around the searched words, so a visitor sees why an item
     * whose name lacks them was found: "…music at home, in the garden or while…".
     *
     * @param array<int, string> $words
     */
    public static function snippet(?string $html, array $words): ?string
    {
        $text = self::plainText((string)$html);
        if ($text === '' || !$words) {
            return null;
        }

        $fragments = [];
        foreach ($words as $word) {
            if (count($fragments) >= self::MAX_SNIPPET_FRAGMENTS) {
                break;
            }

            if (!preg_match(self::wordPattern($word), $text, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $offset = mb_strlen(substr($text, 0, $match[0][1]));
            if (self::isInsideFragment($offset, $fragments)) {
                continue;
            }

            $fragments[] = self::fragmentAround($text, $offset, mb_strlen($match[0][0]));
        }

        if (!$fragments) {
            return null;
        }

        $fragments  = self::mergeOverlapping($fragments);
        $textLength = mb_strlen($text);

        return implode(' ', array_map(
            static fn (array $fragment) => ($fragment['start'] > 0 ? '…' : '')
                .mb_substr($text, $fragment['start'], $fragment['end'] - $fragment['start'])
                .($fragment['end'] < $textLength ? '…' : ''),
            $fragments
        ));
    }

    public static function plainText(string $html): string
    {
        $text = preg_replace('/<[^>]*>/u', ' ', $html) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function wordPattern(string $word): string
    {
        return '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'/iu';
    }

    /**
     * @param array<int, array{start: int, end: int}> $fragments
     */
    private static function isInsideFragment(int $offset, array $fragments): bool
    {
        foreach ($fragments as $fragment) {
            if ($offset >= $fragment['start'] && $offset < $fragment['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{start: int, end: int}> $fragments
     *
     * @return array<int, array{start: int, end: int}>
     */
    private static function mergeOverlapping(array $fragments): array
    {
        usort($fragments, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $merged = [];
        foreach ($fragments as $fragment) {
            $last = array_key_last($merged);
            if ($last !== null && $fragment['start'] <= $merged[$last]['end'] + 1) {
                $merged[$last]['end'] = max($merged[$last]['end'], $fragment['end']);
                continue;
            }
            $merged[] = $fragment;
        }

        return $merged;
    }

    /**
     * @return array{start: int, end: int}
     */
    private static function fragmentAround(string $text, int $offset, int $length): array
    {
        $textLength = mb_strlen($text);

        $start = max(0, $offset - self::SNIPPET_CONTEXT_CHARACTERS);
        if ($start > 0) {
            $space = mb_strpos($text, ' ', $start);
            $start = $space !== false && $space < $offset ? $space + 1 : $start;
        }

        $end = min($textLength, $offset + $length + self::SNIPPET_CONTEXT_CHARACTERS);
        if ($end < $textLength) {
            $space = mb_strrpos(mb_substr($text, 0, $end), ' ');
            $end   = $space !== false && $space > $offset + $length ? $space : $end;
        }

        return ['start' => $start, 'end' => $end];
    }
}
