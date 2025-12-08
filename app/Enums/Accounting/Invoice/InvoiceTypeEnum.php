<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:40:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Invoice;

use App\Enums\EnumHelperTrait;

enum InvoiceTypeEnum: string
{
    use EnumHelperTrait;
    case INVOICE = 'invoice';
    case REFUND = 'refund';

    public static function labels(): array
    {
        return [
            'invoice' => __('invoice'),
            'refund' => __('refund'),
        ];
    }

    public static function typeIcon(): array
    {
        return [
            'invoice' => [
                'tooltip' => __('Invoice'),
                'icon' => 'fal fa-file-invoice-dollar',
                'class' => '',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'refund' => [
                'tooltip' => __('Refund'),
                'icon' => 'fal fa-hand-holding-usd',
                // 'class'   => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
