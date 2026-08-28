<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDelivery;

use App\Enums\EnumHelperTrait;

enum StockDeliveryCostTypeEnum: string
{
    use EnumHelperTrait;

    case AGENT_INVOICE = 'agent_invoice';
    case SHIPPING = 'shipping';
    case DUTY = 'duty';
    case EXTRA = 'extra';

    public static function labels(): array
    {
        return [
            'agent_invoice' => __('Agent invoice'),
            'shipping'      => __('Shipping cost'),
            'duty'          => __('Duty costs'),
            'extra'         => __('Extra expense'),
        ];
    }

    public function itemCostField(): ?string
    {
        return match ($this) {
            self::SHIPPING => 'cost_shipping',
            self::DUTY     => 'cost_duties',
            self::EXTRA    => 'cost_extra',
            default        => null,
        };
    }
}
