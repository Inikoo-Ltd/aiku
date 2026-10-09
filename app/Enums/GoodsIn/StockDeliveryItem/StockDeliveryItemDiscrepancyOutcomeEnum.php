<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDeliveryItem;

use App\Enums\EnumHelperTrait;

/**
 * How a line that arrived under or over what was expected was closed. A recount stays open until
 * the warehouse answers and someone picks one of the other three.
 */
enum StockDeliveryItemDiscrepancyOutcomeEnum: string
{
    use EnumHelperTrait;

    case RECOUNT_REQUESTED = 'recount_requested';
    case UNIT_ERROR_CORRECTED = 'unit_error_corrected';
    case SUPPLIER_CLAIM = 'supplier_claim';
    case SURPLUS_ACCEPTED = 'surplus_accepted';

    public static function labels(): array
    {
        return [
            'recount_requested'    => __('Recount requested'),
            'unit_error_corrected' => __('Unit error corrected'),
            'supplier_claim'       => __('Supplier claim'),
            'surplus_accepted'     => __('Surplus accepted'),
        ];
    }

    public function isResolved(): bool
    {
        return $this !== self::RECOUNT_REQUESTED;
    }
}
