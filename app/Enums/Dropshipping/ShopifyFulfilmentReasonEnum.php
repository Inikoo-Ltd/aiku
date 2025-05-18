<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum ShopifyFulfilmentReasonEnum: string
{
    use EnumHelperTrait;

    case AWAITING_PAYMENT = 'awaiting_payment';
    case HIGH_RISK_OF_FRAUD = 'high_risk_of_fraud';
    case INCORRECT_ADDRESS = 'incorrect_address';
    case INVENTORY_OUT_OF_STOCK = 'inventory_out_of_stock';
    case OTHER = 'other';

    public static function notes(): array
    {
        return [
            'awaiting_payment'       => __('The fulfillment can\'t be process because payment is pending.'),
            'high_risk_of_fraud'     => __('The fulfillment can\'t be process because of a high risk of fraud.'),
            'incorrect_address'      => __('The fulfillment can\'t be process because of an incorrect address.'),
            'inventory_out_of_stock' => __('The fulfillment can\'t be process because inventory is out of stock.'),
            'other'                  => __('The fulfillment can\'t be process for any other reason.')
        ];
    }
}
