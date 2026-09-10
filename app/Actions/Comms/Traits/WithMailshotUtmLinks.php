<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Traits;

use App\Models\Comms\Mailshot;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait WithMailshotUtmLinks
{
    /**
     * @return array<string, array<string, string>>
     */
    protected function getUtmOverrides(Mailshot $mailshot): array
    {
        $utmLinks = Arr::get($mailshot->data, 'utm_links', []);

        if ($utmLinks === [] && $mailshot->is_second_wave) {
            $utmLinks = Arr::get($mailshot->parentMailshot?->data ?? [], 'utm_links', []);
        }

        return collect($utmLinks)
            ->mapWithKeys(fn (array $link) => [Arr::get($link, 'url') => Arr::get($link, 'utm', [])])
            ->filter()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function getWebsiteDomains(Mailshot $mailshot): array
    {
        return Website::where('group_id', $mailshot->group_id)->pluck('domain')->all();
    }

    /**
     * @param array<int, string> $domains
     */
    protected function isInternalLink(string $url, array $domains): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        foreach ($domains as $domain) {
            $domain = Str::lower($domain);

            if ($host === $domain || Str::endsWith($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, string>> $overrides
     * @return array<string, string>
     */
    protected function getUtmOverrideFor(string $url, array $overrides): array
    {
        [$base, $query, $fragment] = $this->splitUrl($url);

        return Arr::get($overrides, $this->rebuildUrl($base, $query, $fragment))
            ?? Arr::get($overrides, $this->rebuildUrl($base, $query, ''))
            ?? [];
    }

    /**
     * @return array{0: string, 1: array<int, string>, 2: string}
     */
    protected function splitUrl(string $url): array
    {
        $fragment = '';
        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#'.$fragment;
        }

        $query = [];
        if (str_contains($url, '?')) {
            [$url, $queryString] = explode('?', $url, 2);
            $query = array_filter(
                explode('&', $queryString),
                fn (string $parameter) => filled($parameter) && !Str::startsWith(Str::lower($parameter), 'utm_')
            );
        }

        return [$url, $query, $fragment];
    }

    /**
     * @param array<int, string> $query
     */
    protected function rebuildUrl(string $base, array $query, string $fragment): string
    {
        return $base.($query === [] ? '' : '?'.implode('&', $query)).$fragment;
    }
}
