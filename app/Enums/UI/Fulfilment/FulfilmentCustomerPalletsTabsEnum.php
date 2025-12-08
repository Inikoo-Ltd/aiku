<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jul 2024 00:55:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\FulfilmentCustomer;

enum FulfilmentCustomerPalletsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case STORING = 'storing';
    case INCOMING = 'incoming';

    case ALL = 'all';
    case RETURNED = 'returned';
    case INCIDENT = 'incident';

    public function blueprint(FulfilmentCustomer $fulfilmentCustomer): array
    {
        return match ($this) {
            FulfilmentCustomerPalletsTabsEnum::STORING => [
                'title' => __('Storing').' ('.($fulfilmentCustomer->number_pallets_status_storing + $fulfilmentCustomer->number_pallets_status_returning).')',
                'icon' => 'fal fa-warehouse-alt',
            ],
            FulfilmentCustomerPalletsTabsEnum::INCOMING => [
                'title' => __('In process')." ({$fulfilmentCustomer->number_pallets_status_in_process})",
                'icon' => 'fal fa-seedling',
            ],
            FulfilmentCustomerPalletsTabsEnum::INCIDENT => [
                'title' => __('Incidents')." ({$fulfilmentCustomer->number_pallets_status_incident})",
                'icon' => 'fal fa-sad-cry',
            ],

            FulfilmentCustomerPalletsTabsEnum::RETURNED => [
                'title' => __('Returned')." ({$fulfilmentCustomer->number_pallets_status_returned})",
                'icon' => 'fal fa-arrow-alt-from-left',
            ],

            FulfilmentCustomerPalletsTabsEnum::ALL => [
                'align' => 'right',
                'type' => 'icon',
                'title' => __('All')." ({$fulfilmentCustomer->number_pallets})",
                'icon' => 'fal fa-align-justify',
            ],

        };
    }
}
