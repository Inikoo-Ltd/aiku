<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Traits\WithEmailLinkContentLabels;
use App\Actions\Comms\Traits\WithMailshotUtmLinks;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class GetMailshotUrls extends OrgAction
{
    use WithEmailLinkContentLabels;
    use WithMailshotUtmLinks;

    public function handle(Mailshot $mailshot): array
    {
        $settings  = GetMailshotUtmSettings::run($mailshot);
        $overrides = $this->getUtmOverrides($mailshot);
        $domains   = $this->getWebsiteDomains($mailshot);
        $roles     = $this->getContentLabelsByUrl($mailshot->email?->liveSnapshot?->compiled_layout, $domains);

        $links = collect($this->extractUrls($mailshot->email?->unpublishedSnapshot?->layout ?? []))
            ->map(fn (string $url) => [
                'url'                  => $url,
                'utm'                  => $this->getUtmOverrideFor($url, $overrides),
                'roles'                => Arr::get($roles, $url, []),
                'is_internal'          => $this->isInternalLink($url, $domains),
                'is_tracking_redirect' => $this->isTrackingRedirect($url),
            ])
            ->values()
            ->all();

        return [
            'settings' => [
                'is_enabled'       => $settings['is_enabled'],
                'source'           => Arr::get($settings, 'parameters.'.MailshotUtmParameterEnum::SOURCE->value),
                'medium'           => Arr::get($settings, 'parameters.'.MailshotUtmParameterEnum::MEDIUM->value),
                'campaign'         => Arr::get($settings, 'parameters.'.MailshotUtmParameterEnum::CAMPAIGN->value),
                'campaign_id'      => Arr::get($settings, 'parameters.'.MailshotUtmParameterEnum::ID->value),
                'default_campaign' => $settings['default_campaign'],
            ],
            'links'  => $links,
            'fields' => $this->getFields($settings),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getContentLabelsByUrl(?string $emailHtmlBody, array $domains): array
    {
        if (blank($emailHtmlBody) || !preg_match_all(self::ANCHOR_PATTERN, $emailHtmlBody, $matches)) {
            return [];
        }

        $counters = [];
        $labels   = [];

        foreach ($matches[0] as $anchor) {
            $url = $this->getAnchorUrl($anchor);

            if ($url === null || !$this->isInternalLink($url, $domains)) {
                continue;
            }

            $labels[$url][] = $this->getContentLabel($anchor, $counters);
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function extractUrls(array $layout): array
    {
        $urls = [];

        array_walk_recursive($layout, function ($value, $key) use (&$urls) {
            if (!is_string($value)) {
                return;
            }

            if ($key === 'href') {
                $urls[] = $value;

                return;
            }

            if (preg_match_all('/href=["\']([^"\']+)["\']/i', $value, $matches)) {
                $urls = array_merge($urls, $matches[1]);
            }
        });

        return collect($urls)
            ->map(fn (string $url) => trim(html_entity_decode($url)))
            ->filter(fn (string $url) => Str::startsWith(Str::lower($url), ['http://', 'https://']) && !Str::contains($url, ['{{', '[unsubscribe]'], true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, label: string, hint: string, placeholder: string}>
     */
    private function getFields(array $settings): array
    {
        return array_map(fn (MailshotUtmParameterEnum $parameter) => [
            'name'        => $parameter->value,
            'label'       => $parameter->label(),
            'hint'        => $parameter->hint(),
            'placeholder' => (string) Arr::get($settings, 'parameters.'.$parameter->value, ''),
        ], MailshotUtmParameterEnum::cases());
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): array
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot);
    }
}
