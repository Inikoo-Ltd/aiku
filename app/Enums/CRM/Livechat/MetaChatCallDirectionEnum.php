<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum MetaChatCallDirectionEnum: string
{
    use EnumHelperTrait;

    case USER_INITIATED = 'user_initiated';
    case BUSINESS_INITIATED = 'business_initiated';

    public static function labels(): array
    {
        return [
            'user_initiated'     => __('Incoming'),
            'business_initiated' => __('Outgoing'),
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'label' => self::labels()[$case->value] ?? $case->value,
                'value' => $case->value,
            ])
            ->toArray();
    }

    /**
     * Meta spells these in upper case on the webhook.
     */
    public static function fromMeta(?string $direction): self
    {
        return match (strtoupper((string) $direction)) {
            'BUSINESS_INITIATED' => self::BUSINESS_INITIATED,
            default              => self::USER_INITIATED,
        };
    }
}
