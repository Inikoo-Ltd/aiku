<?php

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

enum TrafficSourceTypeEnum: string
{
    use EnumHelperTrait;

    case ORGANIC = 'organic';
    case ADS     = 'ads';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIC => __('Organic'),
            self::ADS     => __('Ads'),
        };
    }

    public static function stateIcon(): array
    {
        return [
            self::ORGANIC->value => [
                'tooltip' => __('Organic'),
                'icon'    => 'fal fa-leaf',
                'class'   => 'text-green-500'
            ],
            self::ADS->value => [
                'tooltip' => __('Ads'),
                'icon'    => 'fal fa-bullhorn',
                'class'   => 'text-blue-500'
            ]
        ];
    }
}
