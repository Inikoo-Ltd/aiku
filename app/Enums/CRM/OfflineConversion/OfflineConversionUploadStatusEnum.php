<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:02:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Poll;

use App\Enums\EnumHelperTrait;

enum OfflineConversionUploadStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING  = 'pending';
    case UPLOADED = 'uploaded';
    case FAILED   = 'failed';
    case SKIPPED  = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING  => __('Pending'),
            self::UPLOADED => __('Uploaded'),
            self::FAILED   => __('Failed'),
            self::SKIPPED  => __('Skipped'),
        };
    }

    public static function stateIcon(): array
    {
        return [
            self::PENDING->value => [
                'tooltip' => __('Pending'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-yellow-500'
            ],
            self::UPLOADED->value => [
                'tooltip' => __('Uploaded'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500'
            ],
            self::FAILED->value => [
                'tooltip' => __('Failed'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500'
            ],
            self::SKIPPED->value => [
                'tooltip' => __('Skipped'),
                'icon'    => 'fal fa-forward',
                'class'   => 'text-gray-500'
            ]
        ];
    }
}
