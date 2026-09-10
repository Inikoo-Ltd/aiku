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

class GetMailshotUtmSettings
{
    use AsAction;

    public const DEFAULT_SOURCE = 'newsletter';
    public const DEFAULT_MEDIUM = 'email';

    /**
     * @return array{is_enabled: bool, parameters: array<string, string>, default_campaign: string}
     */
    public function handle(Mailshot $mailshot): array
    {
        $settings = Arr::get($mailshot->data, 'utm', []);

        if ($settings === [] && $mailshot->is_second_wave) {
            $settings = Arr::get($mailshot->parentMailshot?->data ?? [], 'utm', []);
        }

        return [
            'is_enabled'       => (bool) Arr::get($settings, 'is_enabled', true),
            'default_campaign' => $this->getDefaultCampaign($mailshot),
            'parameters'       => [
                MailshotUtmParameterEnum::SOURCE->value   => Arr::get($settings, 'source') ?: self::DEFAULT_SOURCE,
                MailshotUtmParameterEnum::MEDIUM->value   => Arr::get($settings, 'medium') ?: self::DEFAULT_MEDIUM,
                MailshotUtmParameterEnum::CAMPAIGN->value => Arr::get($settings, 'campaign') ?: $this->getDefaultCampaign($mailshot),
                MailshotUtmParameterEnum::ID->value       => $this->getCampaignId($mailshot),
            ],
        ];
    }

    public function getDefaultCampaign(Mailshot $mailshot): string
    {
        return Str::slug($mailshot->name ?: $mailshot->subject, '_');
    }

    private function getCampaignId(Mailshot $mailshot): string
    {
        $date = $mailshot->sent_at
            ?? $mailshot->start_sending_at
            ?? $mailshot->scheduled_at
            ?? $mailshot->date
            ?? now();

        return $date->format('Y-m-d');
    }
}
