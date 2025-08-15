<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 15:36:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Charge;

use App\Enums\EnumHelperTrait;

enum ChargeTriggerEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case ORDER = 'order';
    case PAYMENT_ACCOUNT = 'payment-account';
    case SELECTED_BY_CUSTOMER = 'selected-by-customer';
    case SELECTED_BY_USER = 'selected-by-user';


    public static function labels(): array
    {
        return [
            'product    '          => __('Product'),
            'order'                => __('Order'),
            'payment-account'      => __('Payment Type'),
            'selected-by-customer' => __('Selected by customer'),
            'selected-by-user'     => __('Selected by user'),
        ];
    }


}
