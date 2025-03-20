<?php
/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-16h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
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
 */
class ItemizedInvoiceTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {


        $palletRef = null;
        $handlingDate = null;
        $palletRoute = null;

        if ($this->model_type == 'Service') {
            if (!empty($this->data['pallet_id'])) {
                $pallet = Pallet::find($this->data['pallet_id']);
                $palletRef = $pallet->reference;
                $palletRoute = [
                    'name' => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                    'parameters'    => [
                        'organisation'          => $pallet->organisation->slug,
                        'fulfilment'            => $pallet->fulfilment->slug,
                        'fulfilmentCustomer'    => $pallet->fulfilmentCustomer->slug,
                        'pallet'                 => $pallet->slug
                    ]
                ];
            }

            if (!empty($this->data['date'])) {
                $handlingDate = Carbon::parse($this->data['date'])->format('d M Y');
            }
        } elseif ($this->model_type == 'Rental') {
            
        }

        return [
            'type'                      => $this->model_type,
            'code'                      => $this->code,
            'description'               => $this->name,
            'quantity'                  => (int) $this->quantity,
            'net_amount'                => $this->net_amount,
            'currency_code'             => $this->currency_code,
            'in_process'                => $this->in_process,
            'pallet'                    => $palletRef,
            'handling_date'             => $handlingDate,
            'palletRoute'               => $palletRoute,
        ];
    }
}
