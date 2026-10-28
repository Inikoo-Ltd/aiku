<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SiteAudit;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Str;

class AuditedHtml
{
    /**
     * @param  array<int, string>  $hrefs
     * @param  array<int, array{hreflang: string, href: string}>  $hreflangs
     * @param  array<int, array{type: 'link'|'image', href: string, text: ?string, near_text: ?string}>  $contentLinks  the links and images inside <main>, the page's own content without the header, menu and footer every page shares (the whole page when it has no <main>), with the link text or image alt and the text near an image, to tell where a broken one is
     */
    private function __construct(
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly ?string $canonical,
        public readonly ?string $robotsMeta,
        public readonly int $h1Count,
        public readonly int $imagesWithoutAlt,
        public readonly array $hrefs,
        public readonly array $hreflangs,
        public readonly array $contentLinks
    ) {
    }

    public static function parse(string $html): self
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $metaDescription = null;
        $robotsMeta      = null;

        foreach ($document->querySelectorAll('meta[name]') as $meta) {
            $name = strtolower(trim($meta->getAttribute('name')));

            if ($name === 'description') {
                $metaDescription ??= self::clean($meta->getAttribute('content'));
            } elseif ($name === 'robots') {
                $robotsMeta ??= self::clean($meta->getAttribute('content'));
            }
        }

        $canonical = null;
        $hreflangs = [];

        foreach ($document->querySelectorAll('link[rel][href]') as $link) {
            $rel = preg_split('/\s+/', strtolower($link->getAttribute('rel')));

            if (in_array('canonical', $rel, true)) {
                $canonical ??= self::clean($link->getAttribute('href'));
            } elseif (in_array('alternate', $rel, true) && $link->hasAttribute('hreflang')) {
                $hreflangs[] = [
                    'hreflang' => trim($link->getAttribute('hreflang')),
                    'href'     => trim($link->getAttribute('href')),
                ];
            }
        }

        $imagesWithoutAlt = 0;

        foreach ($document->querySelectorAll('img') as $image) {
            if (!$image->hasAttribute('alt')) {
                $imagesWithoutAlt++;
            }
        }

        $hrefs = self::followedHrefs($document->querySelectorAll('a[href]'));

        return new self(
            title: self::clean($document->querySelector('head title')?->textContent),
            metaDescription: $metaDescription,
            canonical: $canonical,
            robotsMeta: $robotsMeta,
            h1Count: $document->querySelectorAll('h1')->length,
            imagesWithoutAlt: $imagesWithoutAlt,
            hrefs: $hrefs,
            hreflangs: $hreflangs,
            contentLinks: self::contentLinks($document->querySelector('main') ?? $document)
        );
    }

    public function isNoindex(): bool
    {
        return $this->robotsMeta !== null && str_contains(strtolower($this->robotsMeta), 'noindex');
    }

    /**
     * @param  iterable<Element>  $anchors
     *
     * @return array<int, string>
     */
    private static function followedHrefs(iterable $anchors): array
    {
        $hrefs = [];

        foreach ($anchors as $anchor) {
            if (!self::isNofollow($anchor)) {
                $hrefs[] = trim($anchor->getAttribute('href'));
            }
        }

        return array_values(array_unique($hrefs));
    }

    /**
     * @return array<int, array{type: 'link'|'image', href: string, text: ?string, near_text: ?string}>
     */
    private static function contentLinks(HTMLDocument|Element $content): array
    {
        $links = [];

        foreach ($content->querySelectorAll('a[href]') as $anchor) {
            if (!self::isNofollow($anchor)) {
                $links[] = [
                    'type'      => 'link',
                    'href'      => trim($anchor->getAttribute('href')),
                    'text'      => self::clean($anchor->textContent) ?? self::clean($anchor->querySelector('img[alt]')?->getAttribute('alt')) ?? self::clean($anchor->getAttribute('aria-label') ?? $anchor->getAttribute('title')),
                    'near_text' => null,
                ];
            }
        }

        foreach ($content->querySelectorAll('img[src]') as $image) {
            $links[] = [
                'type'      => 'image',
                'href'      => trim($image->getAttribute('src')),
                'text'      => self::clean($image->getAttribute('alt')),
                'near_text' => self::nearText($image),
            ];
        }

        return $links;
    }

    /**
     * The text of the image's nearest ancestor that has any, up to <main>.
     * ponytail: the first 100 characters of that ancestor, which in a bare section can be a long run of text.
     */
    private static function nearText(Element $image): ?string
    {
        for ($element = $image->parentElement; $element && !in_array($element->localName, ['main', 'body'], true); $element = $element->parentElement) {
            $text = self::clean($element->textContent);

            if ($text !== null) {
                return Str::limit($text, 100);
            }
        }

        return null;
    }

    private static function isNofollow(Element $anchor): bool
    {
        return str_contains(strtolower((string) $anchor->getAttribute('rel')), 'nofollow');
    }

    private static function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
