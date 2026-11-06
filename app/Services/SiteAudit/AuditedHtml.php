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
    private const array CONTENT_FREE_TAGS = ['script', 'style', 'noscript', 'template', 'svg'];

    private const array NON_DESCRIPTIVE_ANCHOR_TEXTS = [
        'click here', 'here', 'read more', 'more', 'learn more', 'more info', 'this', 'link', 'continue', 'see more', 'view more', 'go',
        'hier', 'klicken sie hier', 'mehr', 'weiterlesen', 'mehr erfahren',
        'ici', 'cliquez ici', 'en savoir plus', 'lire la suite', 'plus',
        'aquí', 'haz clic aquí', 'leer más', 'más',
        'qui', 'clicca qui', 'leggi di più', 'di più',
        'klik hier', 'lees meer', 'meer',
        'aqui', 'clique aqui', 'leia mais', 'mais',
        'tutaj', 'kliknij tutaj', 'więcej', 'czytaj więcej',
        'zde', 'klikněte zde', 'více',
        'tu', 'kliknite sem', 'viac',
        'itt', 'kattintson ide', 'tovább',
        'aici', 'click aici', 'citește mai mult',
        'här', 'klicka här', 'läs mer',
        'ovdje', 'kliknite ovdje', 'više',
        'tukaj', 'več',
        'тук', 'още',
        'tästä', 'lue lisää',
        'klik her', 'læs mere',
    ];

    private const array HEAD_ELEMENTS = ['title', 'meta', 'link', 'script', 'style', 'base', 'noscript', 'template'];

    private const int READABILITY_MIN_WORDS = 100;

    private const string SOFT_404_PATTERN = '/\b404\b|not found|page (does not|doesn\'t) exist|nicht gefunden|introuvable|n\'existe pas|no encontrad|non trovat|niet gevonden|não encontrad|nie znaleziono|nenalezen|nenájden|nem található|nu a fost găsit|hittades inte|nije pronađen|ni bil[oa]? najden|ei löytynyt|ikke fundet|не е намерен/iu';

    /**
     * @param  array<int, string>  $hrefs
     * @param  array<int, array{hreflang: string, href: string}>  $hreflangs
     * @param  array<int, string>  $contentHrefs  the links inside <main>, the page's own content without the header, menu and footer every page shares; all links when the page has no <main>
     * @param  array<int, array{href: string, nofollow: bool, text: string, unsafe_blank: bool, position: string}>  $anchors  every link on the page, followed or not
     * @param  array<int, array{src: string, has_size: bool, alt: string|null, position: string}>  $images
     * @param  array<int, string>  $h2s  the text of the first two H2 headings
     * @param  array<int, int>  $headingLevels  the level of every heading, h1 to h6, in page order
     * @param  array<int, string>  $brokenBookmarks  the #fragment links to an id or name the page does not have
     * @param  array<int, string>  $protocolRelativeUrls  the links and resources written as //host/path
     * @param  array<int, string>  $invalidHeadElements  the elements written inside <head> that do not belong there, which end the head early
     * @param  array<string, int>  $socialTagCounts  how many times each og:title, og:description, og:image, twitter:card tag is written
     * @param  array<int, string>  $headTagsInBody  the head tags (title, meta description, robots, canonical) found in the body, where search engines ignore them
     * @param  array<int, string>  $resourceUrls  the src of every image, script, frame and media, and the href of every stylesheet
     * @param  array<int, string>  $formActions
     * @param  array<string, string>  $openGraph  og: property => content
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
        public readonly array $contentHrefs,
        public readonly ?string $h1,
        public readonly int $h2Count,
        public readonly int $titleCount,
        public readonly int $metaDescriptionCount,
        public readonly array $canonicals,
        public readonly array $headTagsInBody,
        public readonly array $anchors,
        public readonly array $images,
        public readonly array $resourceUrls,
        public readonly array $formActions,
        public readonly array $openGraph,
        public readonly bool $hasTwitterCard,
        public readonly ?string $paginationNext,
        public readonly ?string $paginationPrev,
        public readonly int $wordCount,
        public readonly ?string $contentHash,
        public readonly ?int $contentSimhash,
        public readonly bool $hasLoremIpsum,
        public readonly ?string $soft404Phrase,
        public readonly array $h2s,
        public readonly ?string $lang,
        public readonly array $headingLevels,
        public readonly bool $hasAltTextInH1,
        public readonly ?string $metaRefresh,
        public readonly array $brokenBookmarks,
        public readonly int $javascriptLinks,
        public readonly array $protocolRelativeUrls,
        public readonly bool $hasDoctype,
        public readonly int $headCount,
        public readonly int $bodyCount,
        public readonly array $invalidHeadElements,
        public readonly bool $hasViewport,
        public readonly bool $hasCharset,
        public readonly array $socialTagCounts,
        public readonly ?float $readingEase
    ) {
    }

    public static function parse(string $html): self
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $metaDescription      = null;
        $metaDescriptionCount = 0;
        $robotsMeta           = null;
        $openGraph            = [];
        $hasTwitterCard       = false;
        $headTagsInBody       = [];
        $socialTagCounts      = [];
        $hasViewport          = false;

        foreach ($document->querySelectorAll('meta[name], meta[property]') as $meta) {
            $name     = strtolower(trim((string) ($meta->getAttribute('name') ?: $meta->getAttribute('property'))));
            $content  = self::clean($meta->getAttribute('content'));
            $isInHead = self::isInHead($meta);

            if ($name === 'description') {
                $metaDescriptionCount++;
                $metaDescription ??= $content;

                if (!$isInHead) {
                    $headTagsInBody[] = 'meta description';
                }
            } elseif ($name === 'robots') {
                $robotsMeta ??= $content;

                if (!$isInHead) {
                    $headTagsInBody[] = 'meta robots';
                }
            } elseif (str_starts_with($name, 'og:') && $content !== null) {
                $openGraph[$name] ??= $content;
            } elseif ($name === 'twitter:card') {
                $hasTwitterCard = true;
            } elseif ($name === 'viewport') {
                $hasViewport = true;
            }

            if (in_array($name, ['og:title', 'og:description', 'og:image', 'twitter:card'], true)) {
                $socialTagCounts[$name] = ($socialTagCounts[$name] ?? 0) + 1;

                if (!$isInHead) {
                    $headTagsInBody[] = 'social tags';
                }
            }
        }

        $metaRefresh = null;

        foreach ($document->querySelectorAll('meta[http-equiv]') as $meta) {
            if (strtolower(trim((string) $meta->getAttribute('http-equiv'))) === 'refresh') {
                $metaRefresh ??= self::clean($meta->getAttribute('content')) ?? '';
            }
        }

        $hasCharset = $document->querySelector('meta[charset]') !== null
            || preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?content-type[^>]+charset=/i', $html) === 1;

        $canonicals     = [];
        $hreflangs      = [];
        $paginationNext = null;
        $paginationPrev = null;
        $resourceUrls   = [];

        foreach ($document->querySelectorAll('link[rel][href]') as $link) {
            $rel  = preg_split('/\s+/', strtolower($link->getAttribute('rel')));
            $href = trim($link->getAttribute('href'));

            if (in_array('canonical', $rel, true)) {
                $canonicals[] = $href;

                if (!self::isInHead($link)) {
                    $headTagsInBody[] = 'canonical';
                }
            } elseif (in_array('alternate', $rel, true) && $link->hasAttribute('hreflang')) {
                $hreflangs[] = [
                    'hreflang' => trim($link->getAttribute('hreflang')),
                    'href'     => $href,
                ];

                if (!self::isInHead($link)) {
                    $headTagsInBody[] = 'hreflang';
                }
            } elseif (in_array('next', $rel, true)) {
                $paginationNext ??= $href;
            } elseif (in_array('prev', $rel, true) || in_array('previous', $rel, true)) {
                $paginationPrev ??= $href;
            } elseif (in_array('stylesheet', $rel, true)) {
                $resourceUrls[] = $href;
            }
        }

        $titles = array_values(array_filter(
            iterator_to_array($document->querySelectorAll('title')),
            fn (Element $title) => $title->closest('svg') === null
        ));

        foreach ($titles as $title) {
            if (!self::isInHead($title)) {
                $headTagsInBody[] = 'title';
            }
        }

        $imagesWithoutAlt = 0;
        $images           = [];

        foreach ($document->querySelectorAll('img') as $image) {
            if (!$image->hasAttribute('alt')) {
                $imagesWithoutAlt++;
            }

            $src = trim((string) ($image->getAttribute('src') ?: $image->getAttribute('data-src')));

            if ($src !== '' && !str_starts_with(strtolower($src), 'data:')) {
                $images[] = [
                    'src'      => $src,
                    'has_size' => $image->hasAttribute('width') && $image->hasAttribute('height'),
                    'alt'      => $image->hasAttribute('alt') ? trim((string) $image->getAttribute('alt')) : null,
                    'position' => self::position($image),
                ];
            }
        }

        foreach ($document->querySelectorAll('script[src], iframe[src], video[src], audio[src], source[src], embed[src]') as $element) {
            $resourceUrls[] = trim($element->getAttribute('src'));
        }

        foreach ($resourceUrls as $resourceUrl) {
            if (str_starts_with($resourceUrl, '//')) {
                $protocolRelativeUrls[] = $resourceUrl;
            }
        }

        $headingLevels = [];

        foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, h6') as $heading) {
            $headingLevels[] = (int) substr(strtolower($heading->tagName), 1);
        }

        $hasAltTextInH1 = false;

        foreach ($document->querySelectorAll('h1 img[alt]') as $image) {
            $hasAltTextInH1 = $hasAltTextInH1 || self::clean($image->getAttribute('alt')) !== null;
        }

        $rawHtml = self::withoutCommentsAndScripts($html);

        $paragraphs = [];

        foreach ($document->querySelectorAll('p') as $paragraph) {
            if (($paragraphText = self::clean($paragraph->textContent)) !== null) {
                $paragraphs[] = $paragraphText;
            }
        }

        $paragraphText  = implode(' ', $paragraphs);
        $paragraphWords = preg_match_all('/[\p{L}\p{N}]+/u', $paragraphText);

        $formActions = [];

        foreach ($document->querySelectorAll('form[action]') as $form) {
            $formActions[] = trim($form->getAttribute('action'));
        }

        $anchors              = [];
        $javascriptLinks      = 0;
        $fragmentTargets      = [];
        $brokenBookmarks      = [];
        $protocolRelativeUrls = [];

        foreach ($document->querySelectorAll('[id], a[name]') as $target) {
            $fragmentTargets[(string) ($target->getAttribute('id') ?: $target->getAttribute('name'))] = true;
        }

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if (preg_match('/^javascript:/i', $href)) {
                $javascriptLinks++;
            } elseif (preg_match('/^#([^!\/].*)$/', $href, $fragment) && !isset($fragmentTargets[rawurldecode($fragment[1])])) {
                $brokenBookmarks[] = $href;
            } elseif (str_starts_with($href, '//')) {
                $protocolRelativeUrls[] = $href;
            }

            $anchors[] = [
                'href'         => trim($anchor->getAttribute('href')),
                'nofollow'     => self::isNofollow($anchor),
                'text'         => self::anchorText($anchor),
                'unsafe_blank' => self::opensUnsafeBlank($anchor),
                'position'     => self::position($anchor),
            ];
        }

        $hrefs = self::followedHrefs($document->querySelectorAll('a[href]'));
        $h1    = self::clean($document->querySelector('h1')?->textContent);
        $h2s   = [];

        foreach ($document->querySelectorAll('h2') as $h2) {
            if (count($h2s) < 2 && ($h2Text = self::clean($h2->textContent)) !== null) {
                $h2s[] = $h2Text;
            }
        }
        $title = self::clean($document->querySelector('head title')?->textContent);

        $contentHrefs = $document->querySelector('main') ? self::followedHrefs($document->querySelectorAll('main a[href]')) : $hrefs;
        $text         = self::contentText($document);
        $words        = preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches) ? $matches[0] : [];

        return new self(
            title: $title,
            metaDescription: $metaDescription,
            canonical: self::clean($canonicals[0] ?? null),
            robotsMeta: $robotsMeta,
            h1Count: $document->querySelectorAll('h1')->length,
            imagesWithoutAlt: $imagesWithoutAlt,
            hrefs: $hrefs,
            hreflangs: $hreflangs,
            contentHrefs: $contentHrefs,
            h1: $h1,
            h2Count: $document->querySelectorAll('h2')->length,
            titleCount: count($titles),
            metaDescriptionCount: $metaDescriptionCount,
            canonicals: array_values(array_unique(array_filter($canonicals))),
            headTagsInBody: array_values(array_unique($headTagsInBody)),
            anchors: $anchors,
            images: $images,
            resourceUrls: array_values(array_unique(array_filter($resourceUrls))),
            formActions: array_values(array_unique(array_filter($formActions))),
            openGraph: $openGraph,
            hasTwitterCard: $hasTwitterCard,
            paginationNext: self::clean($paginationNext),
            paginationPrev: self::clean($paginationPrev),
            wordCount: count($words),
            contentHash: $words ? md5(implode(' ', $words)) : null,
            contentSimhash: $words ? self::simhash($words) : null,
            hasLoremIpsum: str_contains(mb_strtolower($text), 'lorem ipsum'),
            soft404Phrase: self::soft404Phrase($title, $h1),
            h2s: $h2s,
            lang: self::clean($document->documentElement?->getAttribute('lang')),
            headingLevels: $headingLevels,
            hasAltTextInH1: $hasAltTextInH1,
            metaRefresh: $metaRefresh,
            brokenBookmarks: array_values(array_unique($brokenBookmarks)),
            javascriptLinks: $javascriptLinks,
            protocolRelativeUrls: array_values(array_unique($protocolRelativeUrls)),
            hasDoctype: preg_match('/^\s*(\x{FEFF})?\s*<!doctype\s+html/iu', $rawHtml) === 1,
            headCount: preg_match_all('/<head(\s[^>]*)?>/i', $rawHtml),
            bodyCount: preg_match_all('/<body(\s[^>]*)?>/i', $rawHtml),
            invalidHeadElements: self::invalidHeadElements($rawHtml),
            hasViewport: $hasViewport,
            hasCharset: $hasCharset,
            socialTagCounts: $socialTagCounts,
            readingEase: $paragraphWords >= self::READABILITY_MIN_WORDS ? self::readingEase($paragraphText, $paragraphWords) : null
        );
    }

    /**
     * The markup without comments and the content of scripts and styles, so tags written inside them
     * are not counted as the page's own.
     */
    private static function withoutCommentsAndScripts(string $html): string
    {
        return (string) preg_replace(['/<!--.*?-->/s', '/<(script|style|template)\b[^>]*>.*?<\/\1>/is'], ['', '<$1></$1>'], $html);
    }

    /**
     * The elements written between <head> and </head> that browsers only allow in the body. A browser
     * meeting one closes the head there, so every tag after it, canonical and hreflang included, is
     * read as part of the body and ignored by search engines.
     *
     * @return array<int, string>
     */
    private static function invalidHeadElements(string $rawHtml): array
    {
        if (!preg_match('/<head(\s[^>]*)?>(.*?)<\/head>/is', $rawHtml, $head)) {
            return [];
        }

        preg_match_all('/<([a-z][a-z0-9-]*)\b/i', $head[2], $tags);

        return array_values(array_unique(array_filter(
            array_map('strtolower', $tags[1]),
            fn (string $tag) => !in_array($tag, self::HEAD_ELEMENTS, true)
        )));
    }

    /**
     * Flesch reading ease of the page's paragraphs, the prose a visitor reads rather than product
     * names and menus: 206.835 - 1.015 x words per sentence - 84.6 x syllables
     * per word, with syllables counted as groups of vowels. It is defined for English only.
     */
    private static function readingEase(string $text, int $wordCount): float
    {
        $sentences = max(1, preg_match_all('/[^.!?]+[.!?]+/u', $text) ?: 1);
        $syllables = 0;

        preg_match_all('/[a-z]+/i', mb_strtolower($text), $latinWords);

        foreach ($latinWords[0] as $word) {
            $word       = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word) ?: $word;
            $syllables += max(1, preg_match_all('/[aeiouy]+/', $word));
        }

        $words = max(1, count($latinWords[0]) ?: $wordCount);

        return round(206.835 - 1.015 * ($wordCount / $sentences) - 84.6 * ($syllables / $words), 1);
    }

    public function isNoindex(): bool
    {
        return $this->robotsMeta !== null && str_contains(strtolower($this->robotsMeta), 'noindex');
    }

    public function isNofollowPage(): bool
    {
        return $this->robotsMeta !== null && preg_match('/\b(nofollow|none)\b/i', $this->robotsMeta) === 1;
    }

    public static function isNonDescriptiveAnchorText(string $text): bool
    {
        $text = trim(preg_replace('/[^\p{L}\p{N}\s\']+/u', '', mb_strtolower($text)));

        return in_array($text, self::NON_DESCRIPTIVE_ANCHOR_TEXTS, true);
    }

    /**
     * Charikar's simhash of the page's three-word shingles: pages whose hashes differ in only a few
     * of the 64 bits share most of their text.
     *
     * @param  array<int, string>  $words
     */
    private static function simhash(array $words): int
    {
        $weights = array_fill(0, 64, 0);
        $count   = count($words);

        for ($index = 0; $index < max(1, $count - 2); $index++) {
            $hash = unpack('J', substr(md5(implode(' ', array_slice($words, $index, 3)), true), 0, 8))[1];

            for ($bit = 0; $bit < 64; $bit++) {
                $weights[$bit] += (($hash >> $bit) & 1) ? 1 : -1;
            }
        }

        $simhash = 0;

        foreach ($weights as $bit => $weight) {
            if ($weight > 0) {
                $simhash |= 1 << $bit;
            }
        }

        return $simhash;
    }

    private static function soft404Phrase(?string $title, ?string $h1): ?string
    {
        foreach ([$title, $h1] as $text) {
            if ($text !== null && preg_match(self::SOFT_404_PATTERN, $text)) {
                return $text;
            }
        }

        return null;
    }

    public static function visibleText(string $html): string
    {
        return self::contentText(HTMLDocument::createFromString($html, LIBXML_NOERROR));
    }

    /**
     * The visible text of the page's own content: <main> when the page has one, otherwise the body,
     * without scripts, styles and inline SVG. Read last, because it takes those elements out.
     */
    private static function contentText(HTMLDocument $document): string
    {
        $root = $document->querySelector('main') ?? $document->body;

        if (!$root) {
            return '';
        }

        foreach (iterator_to_array($root->querySelectorAll(implode(', ', self::CONTENT_FREE_TAGS))) as $element) {
            $element->remove();
        }

        return (string) self::clean($root->textContent);
    }

    /**
     * Where on the page an element sits, read from the nearest landmark around it, so a link in the
     * menu every page shares can be told apart from a link in the page's own content.
     */
    private static function position(Element $element): string
    {
        return match (true) {
            $element->closest('nav') !== null    => 'nav',
            $element->closest('header') !== null => 'header',
            $element->closest('footer') !== null => 'footer',
            $element->closest('aside') !== null  => 'aside',
            default                              => 'content',
        };
    }

    private static function isInHead(Element $element): bool
    {
        return $element->closest('head') !== null;
    }

    private static function anchorText(Element $anchor): string
    {
        $text = self::clean($anchor->textContent)
            ?? self::clean($anchor->getAttribute('aria-label'))
            ?? self::clean($anchor->getAttribute('title'));

        if ($text === null) {
            foreach ($anchor->querySelectorAll('img[alt]') as $image) {
                $text ??= self::clean($image->getAttribute('alt'));
            }
        }

        return (string) $text;
    }

    private static function opensUnsafeBlank(Element $anchor): bool
    {
        if (strtolower(trim((string) $anchor->getAttribute('target'))) !== '_blank') {
            return false;
        }

        $rel = strtolower((string) $anchor->getAttribute('rel'));

        return !str_contains($rel, 'noopener') && !str_contains($rel, 'noreferrer');
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
