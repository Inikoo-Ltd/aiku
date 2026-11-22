<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use App\Models\Web\WebsiteTextTranslation;

/**
 * The texts people typed into a website's layouts (web blocks, header, top bar, menu, footer, sidebar) and their
 * translations (INI-510). Only strings under a fieldValue, under a key that holds words, count: links, images,
 * icons, embeds and styling are never sent to the AI. A translation is found by the sha1 of the shop's own text
 * exactly as stored, so a text with no row yet stays in the shop's language, string by string.
 */
final class WebsiteTexts
{
    private const array TEXT_KEYS = [
        'value', 'text', 'Text', 'name', 'label', 'title', 'subtitle', 'caption', 'alt', 'imageAlt', 'image_alt',
        'description', 'placeholder', 'copyright', 'text_left', 'text_right', 'close_label', 'headline', 'button_text',
    ];

    private const array RESPONSIVE_KEYS = ['desktop', 'tablet', 'mobile'];

    private const array SKIP_KEYS = [
        'link', 'link_data', 'container', 'source', 'sources', 'icon', 'typeIcon', 'embed_code', 'properties',
        'attributes', 'workshop', 'workshop_url', 'background', 'style', 'styles', 'script',
        'product', 'products', 'family', 'families', 'department', 'departments', 'sub_department', 'sub_departments',
        'collection', 'collections', 'model', 'webpage', 'settings', 'setting', 'products_data', 'paymentData',
        'marketing_material_route', 'product_categories',
    ];

    private const array SKIP_BLOCK_TYPES = ['iframe'];

    private const string KEPT_CODE = '/<(script|style)\b[^>]*>.*?<\/\1\s*>|<!--.*?-->/is';

    private const string HUMAN_ATTRIBUTES = '/(?<![\w-])(alt|title|placeholder|aria-label)\s*=\s*"([^"]*)"/i';

    private const string OPENING_TAG = '/<([a-z][a-z0-9-]*)(\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/i';

    private const array VOID_TAGS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'use', 'stop'];

    private const string PIECE_END = '/(<\/(?:p|div|h[1-6]|li|ul|ol|section|article|header|footer|figure|blockquote|table|tr)\s*>)/i';

    public static function hash(string $text): string
    {
        return sha1($text);
    }

    /**
     * @return array<string, string> source hash => text
     */
    public static function collect(mixed $tree): array
    {
        $texts = [];
        self::walk($tree, false, null, null, function (string $text) use (&$texts) {
            $texts[self::hash($text)] = $text;

            return $text;
        });

        return $texts;
    }

    /**
     * @param array<string, string> $translations source hash => translated text
     */
    public static function apply(mixed $tree, array $translations): mixed
    {
        if (!$translations) {
            return $tree;
        }

        return self::walk($tree, false, null, null, fn (string $text) => $translations[self::hash($text)] ?? $text);
    }

    /**
     * The layout in the visitor's language when they chose one other than the shop's, otherwise as it is.
     */
    public static function forVisitor(int $websiteId, mixed $tree): mixed
    {
        $locale = WebsiteLanguage::translateTo();
        if (!$locale || !is_array($tree)) {
            return $tree;
        }

        return self::apply($tree, self::translations($websiteId, $locale));
    }

    /**
     * One plain text (a webpage title or description) in the visitor's language, or as it is.
     */
    public static function textForVisitor(?int $websiteId, ?string $text): ?string
    {
        $locale = WebsiteLanguage::translateTo();
        if (!$locale || !$websiteId || $text === null || trim($text) === '') {
            return $text;
        }

        return self::translations($websiteId, $locale)[self::hash($text)] ?? $text;
    }

    /**
     * Loaded once per request: the rendered page, header and footer are cached per language, so this runs only
     * when one of them is rebuilt.
     *
     * @return array<string, string>
     */
    public static function translations(int $websiteId, string $locale): array
    {
        $key = "website_texts:$websiteId:$locale";
        $translations = request()->attributes->get($key);

        if (!is_array($translations)) {
            $translations = WebsiteTextTranslation::where('website_id', $websiteId)
                ->where('locale', $locale)
                ->pluck('text', 'source_hash')
                ->all();
            request()->attributes->set($key, $translations);
        }

        return $translations;
    }

    public static function isWords(string $text): bool
    {
        $trimmed = trim($text);

        return $trimmed !== ''
            && !preg_match('/^(https?:|mailto:|tel:|\/|#|rgba?\(|hsla?\(|var\(|(linear|radial|conic)-gradient\()/i', $trimmed)
            && !preg_match('/^\S+\.(jpe?g|png|gif|webp|avif|svg|pdf|mp4|webm)$/i', $trimmed)
            && !preg_match('/^[\w-]+(\.[\w-]+)+$/', $trimmed)
            && self::hasWords($trimmed);
    }

    /**
     * Letters a reader sees: not inside a tag, a kept <style>/<script>/comment or a {{ placeholder }}.
     */
    public static function hasWords(string $text): bool
    {
        $visible = strip_tags((string) preg_replace([self::KEPT_CODE, '/\{\{.*?\}\}/s'], '', $text));

        return preg_match('/\p{L}/u', html_entity_decode($visible)) === 1;
    }

    /**
     * A long masked text cut after closing block tags into pieces of about $maxCharacters, so a page-long custom
     * HTML block is translated in several answers. A piece that is still longer is left whole.
     *
     * @return array<int, string>
     */
    public static function pieces(string $text, int $maxCharacters): array
    {
        if (mb_strlen($text) <= $maxCharacters) {
            return [$text];
        }

        $parts  = preg_split(self::PIECE_END, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $pieces = [];
        $piece  = '';
        for ($i = 0; $i < count($parts); $i += 2) {
            $segment = $parts[$i].($parts[$i + 1] ?? '');
            if ($piece !== '' && mb_strlen($piece) + mb_strlen($segment) > $maxCharacters) {
                $pieces[] = $piece;
                $piece    = '';
            }
            $piece .= $segment;
        }
        if ($piece !== '') {
            $pieces[] = $piece;
        }

        return $pieces;
    }

    /**
     * False when the shop's text nests its tags properly and the translation does not, e.g. <b><i>x</b></i>.
     */
    public static function nestsLike(string $source, string $translated): bool
    {
        return !self::isWellNested($source) || self::isWellNested($translated);
    }

    private static function isWellNested(string $text): bool
    {
        preg_match_all('/<(\/?)([a-z][a-z0-9-]*)(?:\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*)?(\/?)>/i', $text, $tags, PREG_SET_ORDER);
        $open = [];
        foreach ($tags as [, $closing, $name, $selfClosing]) {
            $name = strtolower($name);
            if ($selfClosing || in_array($name, self::VOID_TAGS, true)) {
                continue;
            }
            if (!$closing) {
                $open[] = $name;
            } elseif (array_pop($open) !== $name) {
                return false;
            }
        }

        return $open === [];
    }

    /**
     * What goes to the AI: <style>, <script> and comments become <!--aiku-keep-N-->, and every tag keeps only its
     * name, a data-k="N" reference and its alt, title, placeholder and aria-label, so links, classes and image
     * addresses never reach the AI and a hero full of srcset stays small. unmask() puts everything back.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function mask(string $text): array
    {
        $kept   = [];
        $masked = (string) preg_replace_callback(self::KEPT_CODE, function (array $match) use (&$kept) {
            $kept[] = $match[0];

            return '<!--aiku-keep-'.(count($kept) - 1).'-->';
        }, $text);

        $masked = (string) preg_replace_callback(self::OPENING_TAG, function (array $match) use (&$kept) {
            $kept[] = $match[0];
            preg_match_all(self::HUMAN_ATTRIBUTES, $match[2], $attributes, PREG_SET_ORDER);
            $human = implode('', array_map(fn (array $attribute) => ' '.strtolower($attribute[1]).'="'.$attribute[2].'"', $attributes));

            return '<'.$match[1].' data-k="'.(count($kept) - 1).'"'.$human.'>';
        }, $masked);

        return [$masked, $kept];
    }

    /**
     * @param array<int, string> $kept
     */
    public static function unmask(string $text, array $kept): string
    {
        $text = (string) preg_replace_callback('/<[a-z][a-z0-9-]* data-k="(\d+)"([^>]*)>/i', function (array $match) use ($kept) {
            $original = $kept[(int) $match[1]] ?? $match[0];
            preg_match_all(self::HUMAN_ATTRIBUTES, $match[2], $attributes, PREG_SET_ORDER);
            foreach ($attributes as $attribute) {
                $value    = str_replace('"', '&quot;', $attribute[2]);
                $original = (string) preg_replace_callback(
                    '/(?<![\\w-])'.preg_quote($attribute[1], '/').'\\s*=\\s*"[^"]*"/i',
                    fn () => strtolower($attribute[1]).'="'.$value.'"',
                    $original,
                    1
                );
            }

            return $original;
        }, $text);

        foreach ($kept as $index => $code) {
            $text = str_replace('<!--aiku-keep-'.$index.'-->', $code, $text);
        }

        return $text;
    }

    private static function walk(mixed $node, bool $inFieldValue, int|string|null $key, int|string|null $parentKey, callable $map): mixed
    {
        if (is_string($node)) {
            return $inFieldValue && self::isTextKey($key, $parentKey) && self::isWords($node) ? $map($node) : $node;
        }

        if (!is_array($node) || in_array($node['type'] ?? null, self::SKIP_BLOCK_TYPES, true)) {
            return $node;
        }

        foreach ($node as $childKey => $child) {
            if (is_string($childKey) && in_array($childKey, self::SKIP_KEYS, true)) {
                continue;
            }

            $childInFieldValue = $inFieldValue || $childKey === 'fieldValue';
            $node[$childKey] = self::walk($child, $childInFieldValue, is_int($childKey) ? $key : $childKey, is_int($childKey) ? $parentKey : $key, $map);
        }

        return $node;
    }

    private static function isTextKey(int|string|null $key, int|string|null $parentKey): bool
    {
        if (!is_string($key)) {
            return false;
        }

        return in_array($key, self::TEXT_KEYS, true)
            || preg_match('/^textBox\d+$/', $key) === 1
            || (in_array($key, self::RESPONSIVE_KEYS, true) && $parentKey === 'value');
    }
}
