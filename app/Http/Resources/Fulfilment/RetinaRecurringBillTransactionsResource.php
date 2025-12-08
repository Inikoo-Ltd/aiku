<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jul 2024 12:12:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Actions\Utils\Abbreviate;
use App\Models\Billables\Service;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\Space;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $asset_id
 * @property mixed $asset_type*@property mixed $id
 * @property mixed $asset_slug
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $asset_price
 * @property mixed $asset_unit
 * @property mixed $asset_units
 * @property mixed $currency_code
 * @property mixed $quantity
 * @property mixed $id
 * @property mixed $is_auto_assign
 * @property mixed $historic_assets_id
 * @property mixed $temporal_quantity
 * @property mixed $item_type
 * @property mixed $net_amount
 * @property mixed $discount
 * @property mixed $item_id
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $pallet_delivery_id
 * @property mixed $pallet_return_id
 * @property mixed $fulfilment_transaction_id
 */
class RetinaRecurringBillTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $desc_model = '';
        $desc_title = '';
        $desc_after_title = '';
        $desc_route = null;
        // dump($this);
        if ($this->item_type == 'Pallet') {
            $pallet = Pallet::find($this->item_id);
            // dump($pallet);
            if ($pallet) {
                $desc_title = $pallet->reference;

                $desc_model = __('Storage');
                // $desc_route = [
                //     'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                //     'parameters' => [
                //         'organisation'       => $request->route()->originalParameters()['organisation'],
                //         'fulfilment'         => $request->route()->originalParameters()['fulfilment'],
                //         'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                //         'pallet'             => $pallet->slug
                //     ]
                // ];
            }

        } elseif ($this->pallet_delivery_id) {
            $palletDelivery = PalletDelivery::find($this->pallet_delivery_id);
            if ($palletDelivery) {
                $desc_title = $palletDelivery->reference;
                $desc_model = __('Pallet Delivery');
                $desc_route = [
                    'name' => 'retina.fulfilment.storage.pallet_deliveries.show',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->slug,
                    ],
                ];
            }

        } elseif ($this->pallet_return_id) {
            $palletReturn = PalletReturn::find($this->pallet_return_id);
            if ($palletReturn) {
                $desc_title = $palletReturn->reference;
                $desc_model = __('Pallet Return');
                $desc_route = [
                    'name' => 'retina.fulfilment.storage.pallet_returns.show',
                    'parameters' => [
                        'palletReturn' => $palletReturn->slug,
                    ],
                ];
            }
        } elseif ($this->item_type === 'Space') {
            $space = Space::find($this->item_id);
            if ($space) {
                $desc_model = __('Space (parking)');
                $desc_title = $space->reference;
                // TODO: put retina route here
                // $desc_route = [
                //     'name' => 'grp.org.fulfilments.show.crm.customers.show.spaces.show',
                //     'parameters'    => [
                //         'organisation'          => $request->route()->originalParameters()['organisation'],
                //         'fulfilment'            => $request->route()->originalParameters()['fulfilment'],
                //         'fulfilmentCustomer'    => $space->fulfilmentCustomer->slug,
                //         'space'                 => $space->slug
                //     ]
                // ];
            }
        }

        if ($this->start_date) {
            $desc_after_title .= Carbon::parse($this->start_date)->format('d M Y').'-';
        }
        if ($this->end_date) {
            $desc_after_title .= Carbon::parse($this->end_date)->format('d M Y');
        } else {
            $desc_after_title .= __('ongoing');
        }

        $unitAbbreviation = Abbreviate::run($this->asset_unit);
        $editType = null;
        if ($this->item_type == 'Service') {
            $service = Service::find($this->item_id);
            $editType = $service->edit_type ?? null;
        }

        return [
            'id' => $this->id,
            'type' => $this->item_type,
            'type_icon' => $this->typeIcon($this->item_type),
            'asset_id' => $this->asset_id,
            'asset_slug' => $this->asset_slug,
            'asset_code' => $this->asset_code,
            'asset_price' => $this->asset_price,
            'asset_name' => $this->asset_name,
            'asset_unit' => $this->asset_unit,
            'asset_units' => $this->asset_units,
            'currency_code' => $this->currency_code,
            'unit_abbreviation' => $unitAbbreviation,
            'unit_label' => $this->asset_unit,
            'quantity' => $this->quantity * $this->temporal_quantity,
            'total' => $this->net_amount,
            'discount' => (int) $this->discount,
            'edit_type' => $editType,
            'fulfilment_transaction_id' => $this->fulfilment_transaction_id,
            // 'description'        => $description,
            'description' => [
                'model' => $desc_model,
                'title' => $desc_title,
                'route' => $desc_route,
                'after_title' => $desc_after_title,
            ],

        ];
    }

    public function typeIcon($type): ?array
    {
        if ($type == 'Pallet') {
            return [
                'tooltip' => __('Pallet'),
                'icon' => 'fal fa-pallet',
            ];
        } else {
            return null;
        }
    }
}
