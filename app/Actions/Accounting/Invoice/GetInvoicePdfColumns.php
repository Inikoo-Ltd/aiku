<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Models\Accounting\Invoice;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetInvoicePdfColumns
{
    use AsObject;

    public const COLUMNS = [
        'pro_mode'              => false,
        'rrp'                   => false,
        'parts'                 => false,
        'commodity_codes'       => false,
        'barcode'               => false,
        'weight'                => false,
        'country_of_origin'     => false,
        'hide_payment_status'   => false,
        'cpnp'                  => false,
        'group_by_tariff_code'  => false,
        'show_dispatch_totals'  => false,
        'show_batch_code'       => false,
        'separate_out_of_stock' => true,
        'show_discounts'        => true,
    ];

    /**
     * @return array<string, bool>
     */
    public function handle(Invoice $invoice): array
    {
        $shopColumns    = Arr::get($invoice->shop->settings, 'invoicing.download_pdf_columns', []);
        $invoiceColumns = Arr::get($invoice->data, 'pdf_columns', []);

        $columns = [];
        foreach (self::COLUMNS as $key => $default) {
            $columns[$key] = (bool)Arr::get($invoiceColumns, $key, Arr::get($shopColumns, $key, $default));
        }

        return $columns;
    }
}
