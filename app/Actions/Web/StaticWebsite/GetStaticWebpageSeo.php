<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-13h-02m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebpage;
use Dom\HTMLDocument;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What search engines and link previews read from an info website page, taken from the head of the
 * page as it is stored, in the shape the shop webpages' SEO block shows. Only what an info page can
 * carry is returned, so the block leaves out the shop's breadcrumb label, title prefix and suffix.
 */
class GetStaticWebpageSeo
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(StaticWebpage $webpage): array
    {
        $staticWebsite = $webpage->staticWebsite;
        $head          = $this->head((string) $webpage->html);

        $content = fn (string $selector) => $this->filled($head?->querySelector($selector)?->getAttribute('content'));
        $robots  = strtolower((string) $content('meta[name="robots"]'));

        $indexPage  = !str_contains($robots, 'noindex') && !str_contains($robots, 'none');
        $followLink = !str_contains($robots, 'nofollow') && !str_contains($robots, 'none');

        $structuredData = $this->structuredData($head);
        $hreflang       = $this->hreflang($head);

        return array_merge([
            'title'                 => $this->filled($head?->querySelector('title')?->textContent),
            'page_title'            => $content('meta[property="og:title"]'),
            'description'           => $content('meta[name="description"]'),
            'canonical_url'         => $this->filled($head?->querySelector('link[rel="canonical"]')?->getAttribute('href')),
            'url'                   => 'https://www.'.$staticWebsite->domain.$webpage->path,
            'domain'                => 'www.'.$staticWebsite->domain,
            'site_name'             => $content('meta[property="og:site_name"]') ?? $staticWebsite->name,
            'index_page'            => $indexPage,
            'follow_link'           => $followLink,
            'robots'                => ($indexPage ? 'index' : 'noindex').', '.($followLink ? 'follow' : 'nofollow'),
            'share_image'           => [
                'url' => $content('meta[property="og:image"]') ?? $content('meta[name="twitter:image"]'),
                'alt' => $content('meta[property="og:image:alt"]') ?? $content('meta[name="twitter:image:alt"]'),
            ],
            'structured_data'       => $structuredData ? (count($structuredData) === 1 ? $structuredData[0] : $structuredData) : null,
            'structured_data_types' => array_values(array_unique($this->types($structuredData))),
        ], $hreflang ? ['hreflang' => $hreflang] : []);
    }

    private function head(string $html): ?\Dom\Element
    {
        if ($html === '') {
            return null;
        }

        $headEnd = stripos($html, '</head>');

        return HTMLDocument::createFromString($headEnd === false ? $html : substr($html, 0, $headEnd + 7), LIBXML_NOERROR)->head;
    }

    private function filled(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function structuredData(?\Dom\Element $head): array
    {
        $blocks = [];

        foreach ($head?->querySelectorAll('script[type="application/ld+json"]') ?? [] as $script) {
            $decoded = json_decode($script->textContent, true);
            if (is_array($decoded)) {
                $blocks[] = $decoded;
            }
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function types(mixed $node): array
    {
        if (!is_array($node) || $node === []) {
            return [];
        }

        if (array_is_list($node)) {
            return array_merge(...array_map(fn ($child) => $this->types($child), $node));
        }

        $ownTypes = array_filter((array) ($node['@type'] ?? []), 'is_string');

        return array_merge(array_values($ownTypes), $this->types($node['@graph'] ?? null));
    }

    /**
     * @return list<array{hreflang: string, href: string}>
     */
    private function hreflang(?\Dom\Element $head): array
    {
        $alternates = [];

        foreach ($head?->querySelectorAll('link[rel="alternate"][hreflang]') ?? [] as $link) {
            if ($href = $this->filled($link->getAttribute('href'))) {
                $alternates[] = ['hreflang' => (string) $link->getAttribute('hreflang'), 'href' => $href];
            }
        }

        return $alternates;
    }
}
