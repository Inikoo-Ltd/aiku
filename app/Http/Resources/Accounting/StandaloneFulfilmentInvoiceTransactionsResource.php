<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Models\Billables\Service;
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
 * @property mixed $palletRoute
 */
class StandaloneFulfilmentInvoiceTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $palletRef = null;
        $palletRoute = null;
        $editType = null;
        $handlingDate = null;
        if ($this->model_type == 'Service') {
            $service = Service::find($this->model_id);
            $editType = $service->edit_type ?? null;
        }

        $originalPrice = floatval($this->historic_asset_price);
        $discountedPrice = floatval($this->net_amount);

        $discountPercentage = 0;
        if ($originalPrice > 0) {
            $discountPercentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
        }
        if ($this->model_type == 'Service') {
            if (! empty($this->data['pallet_id'])) {
                $pallet = Pallet::find($this->data['pallet_id']);
                $palletRef = $pallet->reference;
                $palletRoute = [
                    'name' => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                    'parameters' => [
                        'organisation' => $pallet->organisation->slug,
                        'fulfilment' => $pallet->fulfilment->slug,
                        'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                        'pallet' => $pallet->slug,
                    ],
                ];
            } else {
                $palletRef = null;
                $palletRoute = null;
            }

            if (! empty($this->data['date'])) {
                $handlingDate = Carbon::parse($this->data['date'])->format('d M Y');
            } else {
                $handlingDate = null;
            }
        }

        return [
            'id' => $this->id,
            'in_process' => $this->in_process,
            'quantity' => (int) $this->quantity,
            'net_amount' => $this->net_amount,
            'gross_amount' => $this->gross_amount,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'historic_asset_id' => $this->historic_asset_id,
            'historic_asset_code' => $this->historic_asset_code,
            'historic_asset_name' => $this->historic_asset_name,
            'historic_asset_price' => $this->historic_asset_price,
            'historic_asset_unit' => $this->historic_asset_unit,
            'historic_asset_units' => $this->historic_asset_units,
            'asset_id' => $this->asset_id,
            'asset_type' => $this->asset_type,
            'asset_type' => $this->asset_type,
            'asset_slug' => $this->asset_slug,
            'currency_code' => $this->currency_code,
            'edit_type' => $editType,
            'discount' => $discountPercentage,
            'pallet' => $palletRef,
            'handling_date' => $handlingDate,
            'palletRoute' => $palletRoute,
            'updateRoute' => [
                'name' => 'grp.models.standalone-invoice-transaction.update',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ],
                'method' => 'patch',
            ],
            'deleteRoute' => [
                'name' => 'grp.models.standalone-invoice-transaction.delete',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ],
                'method' => 'delete',
            ],

        ];
    }
}
