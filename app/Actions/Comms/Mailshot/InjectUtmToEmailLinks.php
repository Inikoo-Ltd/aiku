<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Traits\WithEmailLinkContentLabels;
use App\Actions\Comms\Traits\WithMailshotUtmLinks;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class InjectUtmToEmailLinks
{
    use AsAction;
    use WithEmailLinkContentLabels;
    use WithMailshotUtmLinks;

    public function handle(Mailshot $mailshot, ?string $emailHtmlBody): ?string
    {
        if (blank($emailHtmlBody)) {
            return $emailHtmlBody;
        }

        $settings  = GetMailshotUtmSettings::run($mailshot);
        $overrides = $this->getUtmOverrides($mailshot);

        if (!$settings['is_enabled'] && $overrides === []) {
            return $emailHtmlBody;
        }

        $domains  = $this->getWebsiteDomains($mailshot);
        $counters = [];

        return preg_replace_callback(
            self::ANCHOR_PATTERN,
            function (array $matches) use ($settings, $overrides, $domains, &$counters) {
                $anchor = $matches[0];
                $url    = $this->getAnchorUrl($anchor);

                if ($url === null) {
                    return $anchor;
                }

                $parameters = $this->isInternalLink($url, $domains)
                    ? $this->getParameters($url, $this->getContentLabel($anchor, $counters), $settings, $overrides)
                    : $this->getUtmOverrideFor($url, $overrides);

                return $parameters === [] ? $anchor : $this->tagAnchorLinks($anchor, $parameters);
            },
            $emailHtmlBody
        );
    }

    /**
     * @return array<string, string>
     */
    private function getParameters(string $url, string $contentLabel, array $settings, array $overrides): array
    {
        $parameters = $settings['is_enabled']
            ? array_merge($settings['parameters'], [MailshotUtmParameterEnum::CONTENT->value => $contentLabel])
            : [];

        return array_filter(
            array_merge($parameters, $this->getUtmOverrideFor($url, $overrides)),
            fn ($value) => filled($value)
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function tagAnchorLinks(string $anchor, array $parameters): string
    {
        return preg_replace_callback(
            '/href=(["\'])(.*?)\1/i',
            function (array $matches) use ($parameters) {
                $url = html_entity_decode($matches[2], ENT_QUOTES);

                if (!Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
                    return $matches[0];
                }

                [$base, $query, $fragment] = $this->splitUrl($url);

                foreach (MailshotUtmParameterEnum::values() as $parameter) {
                    if (filled(Arr::get($parameters, $parameter))) {
                        $query[] = $parameter.'='.rawurlencode($parameters[$parameter]);
                    }
                }

                return 'href='.$matches[1].htmlspecialchars($this->rebuildUrl($base, $query, $fragment), ENT_QUOTES).$matches[1];
            },
            $anchor
        );
    }
}
