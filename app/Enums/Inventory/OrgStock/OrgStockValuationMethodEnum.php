<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 23:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStock;

use App\Enums\EnumHelperTrait;

/**
 * Single source of truth for which stock valuation method is the official one.
 * Changing official() re-points sku_value, the visible table columns, the legends
 * and the dormant-stock figure in one move.
 */
enum OrgStockValuationMethodEnum: string
{
    use EnumHelperTrait;

    case FIFO = 'fifo';
    case WAC = 'wac';
    case LPP = 'lpp';

    public static function official(): self
    {
        return once(function () {
            $setting = \Illuminate\Support\Arr::get(group()?->settings ?? [], 'inventory.official_valuation_method');

            $method = self::tryFrom($setting ?? '')
                ?? self::tryFrom(config('inventory.official_valuation_method') ?? '')
                ?? self::FIFO;

            return $method === self::LPP ? self::FIFO : $method;
        });
    }

    public function shortLegend(): string
    {
        if ($this === self::official()) {
            return __('recommended');
        }
        if ($this === self::LPP) {
            return __('not recommended');
        }

        return __('alternative');
    }

    /**
     * @return array<int, self> official method first, the rest after
     */
    public static function ordered(): array
    {
        $official = self::official();

        return array_merge([$official], array_values(array_filter(self::cases(), fn (self $method) => $method !== $official)));
    }

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function fullName(): string
    {
        return match ($this) {
            self::FIFO => __('First In First Out'),
            self::WAC  => __('Weighted Average Cost'),
            self::LPP  => __('Last Purchase Price'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FIFO => __('Stock is valued at the cost of its oldest remaining purchases.'),
            self::WAC  => __('Stock is valued at the average cost of everything on hand.'),
            self::LPP  => __('Stock is valued at the price of the most recent purchase.'),
        };
    }

    public function legend(): string
    {
        return $this->fullName().' — '.$this->description().' '.match (true) {
            $this === self::official() => __('Recommended, the official valuation.'),
            $this === self::LPP        => __('Not recommended, not allowed in the UK.'),
            default                    => __('Allowed alternative.'),
        };
    }

    public function perSkuColumn(): string
    {
        return $this->value.'_per_sku';
    }

    public function stockValueColumn(): string
    {
        return 'org_stock_'.$this->value.'_value';
    }

    public function grpStockValueColumn(): string
    {
        return 'grp_stock_'.$this->value.'_value';
    }

    public function dormantValueColumn(): string
    {
        return $this === self::LPP ? 'value_dormant_stock_1y' : 'value_dormant_stock_1y_'.$this->value;
    }
}
