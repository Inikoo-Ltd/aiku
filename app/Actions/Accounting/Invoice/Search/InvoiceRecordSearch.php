<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:46:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\Search;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Invoice $invoice): void
    {
        if ($invoice->trashed()) {
            if ($invoice->universalSearch) {
                $invoice->universalSearch()->delete();
            }

            return;
        }

        $shop                = $invoice->shop;
        $universalSearchData = [
            'group_id'          => $invoice->group_id,
            'organisation_id'   => $invoice->organisation_id,
            'organisation_slug' => $invoice->organisation->slug,
            'shop_id'           => $shop->id,
            'shop_slug'         => $shop->slug,
            'customer_id'       => $invoice->customer_id,
            'customer_slug'     => $invoice->slug,
            'sections'          => ['accounting', 'ordering'],
            'haystack_tier_1'   => trim($invoice->reference.' '.$invoice->customer_name),
            'keyword'           => $invoice->reference,
            'result'            => [
                'route' => [
                    'name'       => 'grp.org.redirect.invoices',
                    'parameters' => [$invoice->id]
                ],

                'description' => [
                    'label' => $invoice->customer_name,
                ],
                'code'        => [
                    'label' => $invoice->reference,
                ],
                'icon'        => [
                    'icon' => $invoice->type == InvoiceTypeEnum::INVOICE ? 'fal fa-file-invoice-dollar' : 'fal fa-file-minus',
                ],
                'meta'        => [
                    [
                        'label'   => $invoice->type,
                        'tooltip' => __('Type')
                    ],
                    [
                        'type'    => 'date',
                        'label'   => $invoice->date,
                        'tooltip' => __('Date')
                    ],
                    [
                        'type'    => 'currency',
                        'code'    => $invoice->currency->code,
                        'label'   => 'Total: ',
                        'amount'  => $invoice->total_amount,
                        'tooltip' => __('Total amount')
                    ],
                ],
            ]
        ];

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            $universalSearchData['fulfilment_id']   = $shop->fulfilment->id;
            $universalSearchData['fulfilment_slug'] = $shop->fulfilment->slug;
        }


        $invoice->universalSearch()->updateOrCreate(
            [],
            $universalSearchData
        );


        $invoice->retinaSearch()->updateOrCreate(
            [],
            [
                'group_id'        => $invoice->group_id,
                'organisation_id' => $invoice->organisation_id,
                'customer_id'     => $invoice->customer_id,
                'haystack_tier_1' => $invoice->reference,
            ]
        );
    }

}
