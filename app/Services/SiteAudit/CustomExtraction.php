<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SiteAudit;

use Dom\HTMLDocument;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * The custom searches and extractions a crawl was started with, run on the HTML of each page. A search
 * counts how many times a text or pattern appears in the source; an extraction keeps the first values
 * a CSS selector, an XPath or a pattern finds.
 */
class CustomExtraction
{
    public const int VALUES_KEPT = 5;

    public const int VALUE_MAX_LENGTH = 500;

    public const array SEARCH_TYPES = ['text', 'regex'];

    public const array SEARCH_MODES = ['contains', 'not_contains'];

    public const array EXTRACTION_TYPES = ['css', 'xpath', 'regex'];

    public const array EXTRACTION_OUTPUTS = ['text', 'html', 'attribute'];

    /**
     * @param  array<int, array{name: string, mode: string, type: string, value: string}>  $searches
     * @param  array<int, array{name: string, type: string, selector: string, output: string, attribute: string|null}>  $extractions
     *
     * @return array{search: array<int, int>, extract: array<int, array<int, string>>}
     */
    public static function run(string $html, array $searches, array $extractions): array
    {
        $result = ['search' => [], 'extract' => []];

        foreach ($searches as $index => $search) {
            $result['search'][$index] = $search['type'] === 'regex'
                ? (int) @preg_match_all(self::pattern($search['value']), $html)
                : substr_count(mb_strtolower($html), mb_strtolower($search['value']));
        }

        $document      = null;
        $xpathDocument = null;

        foreach ($extractions as $index => $extraction) {
            $result['extract'][$index] = match ($extraction['type']) {
                'regex' => self::regexValues($html, $extraction['selector']),
                'xpath' => self::xpathValues($xpathDocument ??= self::xpathDocument($html), $extraction),
                default => self::cssValues($document ??= HTMLDocument::createFromString($html, LIBXML_NOERROR), $extraction),
            };
        }

        return $result;
    }

    public static function isValidSelector(string $type, string $selector): bool
    {
        try {
            $document = HTMLDocument::createEmpty();

            return match ($type) {
                'regex' => @preg_match(self::pattern($selector), '') !== false,
                'xpath' => @(new DOMXPath(new DOMDocument()))->query($selector) !== false,
                default => $document->querySelectorAll($selector) !== null,
            };
        } catch (Throwable) {
            return false;
        }
    }

    public static function isValidSearch(string $type, string $value): bool
    {
        return $type !== 'regex' || @preg_match(self::pattern($value), '') !== false;
    }

    private static function pattern(string $pattern): string
    {
        return '~'.str_replace('~', '\\~', $pattern).'~isu';
    }

    /**
     * @return array<int, string>
     */
    private static function regexValues(string $html, string $selector): array
    {
        if (!@preg_match_all(self::pattern($selector), $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        return self::kept(array_map(fn (array $match) => $match[1] ?? $match[0], $matches));
    }

    /**
     * @param  array{type: string, selector: string, output: string, attribute: string|null}  $extraction
     *
     * @return array<int, string>
     */
    private static function cssValues(HTMLDocument $document, array $extraction): array
    {
        try {
            $nodes = $document->querySelectorAll($extraction['selector']);
        } catch (Throwable) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            $values[] = match ($extraction['output']) {
                'html'      => (string) $node->innerHTML,
                'attribute' => (string) $node->getAttribute((string) $extraction['attribute']),
                default     => (string) $node->textContent,
            };

            if (count($values) >= self::VALUES_KEPT * 2) {
                break;
            }
        }

        return self::kept($values);
    }

    /**
     * The page read without the HTML namespace, so XPath written as people write it (//div[@id="x"])
     * matches; the HTML5 parser puts every element in the XHTML namespace, which plain XPath misses.
     */
    private static function xpathDocument(string $html): DOMDocument
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return $document;
    }

    /**
     * @param  array{type: string, selector: string, output: string, attribute: string|null}  $extraction
     *
     * @return array<int, string>
     */
    private static function xpathValues(DOMDocument $document, array $extraction): array
    {
        $nodes = @(new DOMXPath($document))->query($extraction['selector']);

        if ($nodes === false) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            $values[] = match (true) {
                !$node instanceof DOMElement          => (string) $node->textContent,
                $extraction['output'] === 'html'      => implode('', array_map(fn ($child) => (string) $document->saveHTML($child), iterator_to_array($node->childNodes))),
                $extraction['output'] === 'attribute' => $node->getAttribute((string) $extraction['attribute']),
                default                               => (string) $node->textContent,
            };

            if (count($values) >= self::VALUES_KEPT * 2) {
                break;
            }
        }

        return self::kept($values);
    }

    /**
     * @param  array<int, string>  $values
     *
     * @return array<int, string>
     */
    private static function kept(array $values): array
    {
        $values = array_values(array_filter(array_map(fn (string $value) => trim(preg_replace('/\s+/u', ' ', $value)), $values), fn (string $value) => $value !== ''));

        return array_map(fn (string $value) => Str::limit($value, self::VALUE_MAX_LENGTH), array_slice($values, 0, self::VALUES_KEPT));
    }
}
