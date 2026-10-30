<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDelivery;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

/**
 * How a stock delivery travels, kept in stock_deliveries.data.delivery_type. Only a full container has a container
 * size; LCL shares one, so it still sails with a bill of lading.
 */
enum StockDeliveryTypeEnum: string
{
    use EnumHelperTrait;

    case CONTAINER = 'container';
    case LCL = 'lcl';
    case AIR = 'air';
    case PARCEL = 'parcel';

    public static function labels(): array
    {
        return [
            'container' => __('Full container'),
            'lcl'       => __('LCL (shared container)'),
            'air'       => __('Air freight'),
            'parcel'    => __('Parcel'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function selectOptions(): array
    {
        return collect(self::labels())->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])->values()->all();
    }

    /**
     * How an agent usually ships, set on its procurement settings: the type a new shipment starts with and the icon
     * its shipments on the way show.
     */
    public static function usualFor(Organisation $agentOrganisation): self
    {
        return self::tryFrom((string) Arr::get($agentOrganisation->settings, 'procurement.default_delivery_type')) ?? self::CONTAINER;
    }

    public function icon(): string
    {
        return match ($this) {
            self::CONTAINER, self::LCL => 'fal fa-ship',
            self::AIR                  => 'fal fa-plane',
            self::PARCEL               => 'fal fa-shipping-fast',
        };
    }

    public function sailsWithBillOfLading(): bool
    {
        return in_array($this, [self::CONTAINER, self::LCL]);
    }
}
