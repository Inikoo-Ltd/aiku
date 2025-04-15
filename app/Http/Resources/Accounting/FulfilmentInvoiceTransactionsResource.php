<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:38:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Fulfilment\Pallet;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $name
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 * @property mixed $model_type
 * @property mixed $asset_id
 */
class FulfilmentInvoiceTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $palletRef    = null;
        $handlingDate = null;
        $palletRoute  = null;

        if ($this->model_type == 'Service') {
            if (!empty($this->data['pallet_id'])) {
                $pallet      = Pallet::find($this->data['pallet_id']);
                $palletRef   = $pallet->reference;
                $palletRoute = [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                    'parameters' => [
                        'organisation'       => $pallet->organisation->slug,
                        'fulfilment'         => $pallet->fulfilment->slug,
                        'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                        'pallet'             => $pallet->slug
                    ]
                ];
            }

            if (!empty($this->data['date'])) {
                $handlingDate = Carbon::parse($this->data['date'])->format('d M Y');
            }
        }


        return [
            'asset_id'      => $this->asset_id,
            'code'          => $this->code,
            'name'          => $this->name,
            'quantity'      => (int)$this->quantity,
            'net_amount'    => $this->net_amount,
            'currency_code' => $this->currency_code,
            'in_process'    => $this->in_process,
            'pallet'        => $palletRef,
            'handling_date' => $handlingDate,
            'palletRoute'   => $palletRoute,

            'refund_route'      => [
                'name'       => 'grp.models.refund.refund_transaction.store',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'full_refund_route' => [
                'name'       => 'grp.models.refund.refund_transaction.full_refund',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ]
            ]
        ];
    }
}
