<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 13:12:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Invoice;

use App\Enums\EnumHelperTrait;

enum InvoicePayStatusEnum: string
{
    use EnumHelperTrait;

    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case UNKNOWN = 'unknown'; // If the invoice is older than 3 years old and has No Payments

    public static function labels(): array
    {
        return [
            'unpaid' => __('Unpaid'),
            'paid'   => __('Paid'),
            'unknown' => __('Unknown payment status'),
        ];
    }

    public static function typeIcon(): array
    {
        return [
            'unpaid' => [
                'tooltip' => __('Unpaid'),
                'icon'    => 'fal fa-circle',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'paid'   => [
                'tooltip' => __('Paid'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-600',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'check-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'unknown' => [
                'tooltip' => __('Unknown'),
                'icon'    => 'fal fa-question-circle',
                'class'   => 'text-gray-500',
                'color'   => 'question-circle',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
