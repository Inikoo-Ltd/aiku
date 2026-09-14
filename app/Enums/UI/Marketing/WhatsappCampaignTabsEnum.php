<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\UI\Marketing;

use App\Enums\EnumHelperTrait;
use App\Models\Comms\WhatsappCampaign;

enum WhatsappCampaignTabsEnum: string
{
    use EnumHelperTrait;

    case SHOWCASE = 'showcase';
    case RECIPIENTS = 'recipients';

    public function blueprint(WhatsappCampaign $campaign): array
    {
        return match ($this) {
            WhatsappCampaignTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            WhatsappCampaignTabsEnum::RECIPIENTS => [
                'title' => __('Recipients')." ({$campaign->recipients_count})",
                'icon'  => 'fal fa-users',
            ],
        };
    }

    public static function navigation(WhatsappCampaign $campaign): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($campaign) {
            return [$case->value => $case->blueprint($campaign)];
        })->all();
    }
}
