<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class InjectUtmToEmailLinks
{
    use AsAction;

    public function handle(Mailshot $mailshot, ?string $emailHtmlBody): ?string
    {
        if (blank($emailHtmlBody)) {
            return $emailHtmlBody;
        }

        $utmLinks = $this->getUtmLinks($mailshot);

        if ($utmLinks === []) {
            return $emailHtmlBody;
        }

        return preg_replace_callback(
            '/href=(["\'])(.*?)\1/i',
            function (array $matches) use ($utmLinks) {
                $url = html_entity_decode($matches[2], ENT_QUOTES);

                if (!Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
                    return $matches[0];
                }

                [$base, $query, $fragment] = $this->splitUrl($url);

                $utm = Arr::get($utmLinks, $this->rebuildUrl($base, $query, $fragment))
                    ?? Arr::get($utmLinks, $this->rebuildUrl($base, $query, ''));

                if (!$utm) {
                    return $matches[0];
                }

                foreach (MailshotUtmParameterEnum::values() as $parameter) {
                    if (filled(Arr::get($utm, $parameter))) {
                        $query[] = $parameter.'='.rawurlencode($utm[$parameter]);
                    }
                }

                return 'href='.$matches[1].htmlspecialchars($this->rebuildUrl($base, $query, $fragment), ENT_QUOTES).$matches[1];
            },
            $emailHtmlBody
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getUtmLinks(Mailshot $mailshot): array
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
     * @return array{0: string, 1: array<int, string>, 2: string}
     */
    private function splitUrl(string $url): array
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
    private function rebuildUrl(string $base, array $query, string $fragment): string
    {
        return $base.($query === [] ? '' : '?'.implode('&', $query)).$fragment;
    }
}
