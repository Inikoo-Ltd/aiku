<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SiteAudit;

use Dom\Element;
use Dom\HTMLDocument;

class AuditedHtml
{
    /**
     * @param  array<int, string>  $hrefs
     */
    private function __construct(
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly ?string $canonical,
        public readonly ?string $robotsMeta,
        public readonly int $h1Count,
        public readonly int $imagesWithoutAlt,
        public readonly array $hrefs
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

        foreach ($document->querySelectorAll('link[rel][href]') as $link) {
            if (in_array('canonical', preg_split('/\s+/', strtolower($link->getAttribute('rel'))), true)) {
                $canonical = self::clean($link->getAttribute('href'));

                break;
            }
        }

        $imagesWithoutAlt = 0;

        foreach ($document->querySelectorAll('img') as $image) {
            if (!$image->hasAttribute('alt')) {
                $imagesWithoutAlt++;
            }
        }

        $hrefs = [];

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            if (!self::isNofollow($anchor)) {
                $hrefs[] = trim($anchor->getAttribute('href'));
            }
        }

        return new self(
            title: self::clean($document->querySelector('head title')?->textContent),
            metaDescription: $metaDescription,
            canonical: $canonical,
            robotsMeta: $robotsMeta,
            h1Count: $document->querySelectorAll('h1')->length,
            imagesWithoutAlt: $imagesWithoutAlt,
            hrefs: array_values(array_unique($hrefs))
        );
    }

    public function isNoindex(): bool
    {
        return $this->robotsMeta !== null && str_contains(strtolower($this->robotsMeta), 'noindex');
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
