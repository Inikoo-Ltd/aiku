<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\UI;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Models\CRM\TrafficSource;

enum TrafficSourceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW    = 'overview';
    case NEWSLETTERS = 'newsletters';
    case CUSTOMERS   = 'customers';
    case ORDERS      = 'orders';

    /**
     * @return array<int, self>
     */
    public static function casesFor(?TrafficSource $trafficSource): array
    {
        return array_values(
            array_filter(
                self::cases(),
                fn (self $case) => $case !== self::NEWSLETTERS || $trafficSource?->type === TrafficSourcesTypeEnum::NEWSLETTER->value
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public static function valuesFor(?TrafficSource $trafficSource): array
    {
        return array_column(self::casesFor($trafficSource), 'value');
    }

    public static function navigation(?TrafficSource $trafficSource = null): array
    {
        return collect(self::casesFor($trafficSource))
            ->mapWithKeys(fn (self $case) => [$case->value => $case->blueprint()])
            ->all();
    }

    public function blueprint(): array
    {
        return match ($this) {
            TrafficSourceTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-chart-network',
            ],
            TrafficSourceTabsEnum::NEWSLETTERS => [
                'title' => __('Newsletters'),
                'icon'  => 'fal fa-newspaper',
            ],
            TrafficSourceTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-users',
            ],
            TrafficSourceTabsEnum::ORDERS => [
                'title' => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
            ],
        };
    }
}
