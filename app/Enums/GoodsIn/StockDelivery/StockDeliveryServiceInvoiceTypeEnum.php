<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDelivery;

use App\Enums\EnumHelperTrait;

/**
 * Tax is import VAT, recovered in the VAT return: it is recorded and paid but never part of the landed cost.
 */
enum StockDeliveryServiceInvoiceTypeEnum: string
{
    use EnumHelperTrait;

    case FREIGHT = 'freight';
    case DUTY = 'duty';
    case TAX = 'tax';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'freight' => __('Freight'),
            'duty'    => __('Customs duty'),
            'tax'     => __('Import VAT'),
            'other'   => __('Other'),
        ];
    }

    public function costType(): ?StockDeliveryCostTypeEnum
    {
        return match ($this) {
            self::FREIGHT => StockDeliveryCostTypeEnum::SHIPPING,
            self::DUTY    => StockDeliveryCostTypeEnum::DUTY,
            self::OTHER   => StockDeliveryCostTypeEnum::EXTRA,
            self::TAX     => null,
        };
    }
}
