<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class GetMailshotUrls extends OrgAction
{
    /**
     * @return array{links: array<int, array{url: string, utm: array<string, string>}>, fields: array<int, array{name: string, label: string, hint: string, placeholder: string}>}
     */
    public function handle(Mailshot $mailshot): array
    {
        $savedUtms = collect(Arr::get($mailshot->data, 'utm_links', []))
            ->keyBy(fn (array $link) => Arr::get($link, 'url'));

        $links = collect($this->extractUrls($mailshot->email?->unpublishedSnapshot?->layout ?? []))
            ->map(fn (string $url) => [
                'url' => $url,
                'utm' => Arr::get($savedUtms->get($url, []), 'utm', []),
            ])
            ->values()
            ->all();

        return [
            'links'  => $links,
            'fields' => $this->getFields($mailshot),
        ];
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
    private function getFields(Mailshot $mailshot): array
    {
        $suggestions = [
            MailshotUtmParameterEnum::SOURCE->value   => 'newsletter',
            MailshotUtmParameterEnum::MEDIUM->value   => 'email',
            MailshotUtmParameterEnum::CAMPAIGN->value => Str::slug($mailshot->name ?: $mailshot->subject, '_'),
            MailshotUtmParameterEnum::ID->value       => $mailshot->date?->format('Y-m-d') ?? '',
            MailshotUtmParameterEnum::TERM->value     => '',
            MailshotUtmParameterEnum::CONTENT->value  => '',
        ];

        return array_map(fn (MailshotUtmParameterEnum $parameter) => [
            'name'        => $parameter->value,
            'label'       => $parameter->label(),
            'hint'        => $parameter->hint(),
            'placeholder' => Arr::get($suggestions, $parameter->value, ''),
        ], MailshotUtmParameterEnum::cases());
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): array
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot);
    }
}
