<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:32:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Models\Inventory\Location;

enum LocationTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case ORG_STOCKS = 'org_stocks';
    case PALLETS = 'pallets';
    case STOCK_MOVEMENTS = 'stock_movements';
    case HISTORY = 'history';

    public static function navigation(?Location $location = null): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($location) {
            $blueprint = $case->blueprint();

            $number = $location ? match ($case) {
                self::ORG_STOCKS => $location->stats?->number_org_stock_slots,
                self::PALLETS    => $location->stats?->number_pallets,
                default          => null,
            } : null;

            if ($number !== null) {
                $blueprint['number'] = $number;
            }

            return [$case->value => $blueprint];
        })->all();
    }


    public function blueprint(): array
    {
        return match ($this) {
            LocationTabsEnum::ORG_STOCKS => [
                'title' => 'SKUs',
                'icon'  => 'fal fa-box',
            ],
            LocationTabsEnum::PALLETS => [
                'title' => __('Pallets'),
                'icon'  => 'fal fa-pallet',
            ],
            LocationTabsEnum::STOCK_MOVEMENTS => [
                'title' => __('Stock movements'),
                'icon'  => 'fal fa-exchange',
            ],
            LocationTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            LocationTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
