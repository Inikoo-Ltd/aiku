<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\PreOrder;

use App\Enums\EnumHelperTrait;

enum PreOrderCancellationReasonEnum: string
{
    use EnumHelperTrait;

    case CUSTOMER_REQUEST = 'customer_request';
    case BALANCE_NOT_PAID = 'balance_not_paid';
    case LATE = 'late';
    case SUPPLIER_CANNOT_SUPPLY = 'supplier_cannot_supply';
    case SUPPLIER_MINIMUM_NOT_MET = 'supplier_minimum_not_met';
    case PALLET_QUOTE_OVER_ESTIMATE = 'pallet_quote_over_estimate';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => __('Cancelled at the customer\'s request'),
            self::BALANCE_NOT_PAID => __('The balance was not paid in time'),
            self::LATE => __('We are late past the estimated dispatch'),
            self::SUPPLIER_CANNOT_SUPPLY => __('Our supplier cannot supply it'),
            self::SUPPLIER_MINIMUM_NOT_MET => __('Not enough pre-orders to meet the supplier\'s minimum order'),
            self::PALLET_QUOTE_OVER_ESTIMATE => __('The pallet delivery quote is above the estimate'),
        };
    }

    /**
     * Our failure to deliver: the customer gets everything back, deposit included.
     */
    public function isFullRefund(): bool
    {
        return in_array($this, [self::LATE, self::SUPPLIER_CANNOT_SUPPLY, self::SUPPLIER_MINIMUM_NOT_MET, self::PALLET_QUOTE_OVER_ESTIMATE]);
    }
}
