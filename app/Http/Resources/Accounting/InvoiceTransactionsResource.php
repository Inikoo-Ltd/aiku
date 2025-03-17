<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:38:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
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
class InvoiceTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {

        $getRoute = function ($type, $assetId) use ($request) {
            $shop = Shop::find($this->shop_id);
            $asset = Asset::find($assetId);
            $org = $request->route('organisation');

            if (str_starts_with($request->route()->getName(), 'retina')) {
                return null;
            }

            $fulfilment = $shop->fulfilment;
            if (!$fulfilment) {
                return match ($type) {
                    'Product' => [
                        'name' => 'grp.org.shops.show.catalogue.products.all_products.show',
                        'parameters' => [
                            'organisation' => $org->slug,
                            'shop' => $shop->slug,
                            'product' => $asset->product->slug
                        ]
                    ],
                    'Charge' => [
                        'name' => 'grp.org.shops.show.billables.charges.show',
                        'parameters' => [
                            'organisation' => $org->slug,
                            'shop' => $shop->slug,
                            'charge' => $asset->charge->slug
                        ]
                    ],
                    default => null,
                };
            }

            $invoice = Invoice::find($this->invoice_id);

            return match ($type) {
                'Product' => [
                    'name' => 'grp.org.fulfilments.show.catalogue.physical_goods.show',
                    'parameters' => [
                        'organisation' => $org->slug,
                        'fulfilment' => $fulfilment->slug,
                        'product' => $asset->product->slug
                    ]
                ],
                'Rental' => [
                    'name' => 'grp.org.fulfilments.show.catalogue.rentals.show',
                    'parameters' => [
                        'organisation' => $org->slug,
                        'fulfilment' => $fulfilment->slug,
                        'rental' => $asset->rental->slug
                    ]
                ],
                'Service' => [
                    'name' => 'grp.org.fulfilments.show.catalogue.services.show',
                    'parameters' => [
                        'organisation' => $org->slug,
                        'fulfilment' => $fulfilment->slug,
                        'service' => $asset->service->slug
                    ]
                ],
                'Space' => [
                    'name' => 'grp.org.fulfilments.show.crm.customers.show.spaces.show',
                    'parameters' => [
                        'organisation' => $org->slug,
                        'fulfilment' => $fulfilment->slug,
                        'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug,
                        'space' => $asset->space->slug
                    ]
                ],
                default => null,
            };
        };

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
        }

        return [
            'code'                      => $this->code,
            'name'                      => $this->name,
            'quantity'                  => (int) $this->quantity,
            'net_amount'                => $this->net_amount,
            'currency_code'             => $this->currency_code,
            'in_process'                => $this->in_process,
            'pallet'                    => $palletRef,
            'handling_date'             => $handlingDate,
            'palletRoute'               => $palletRoute,
            'route_desc'                => $getRoute($this->model_type, $this->id),
            'refund_route'              => [
                'name'       => 'grp.models.invoice_transaction.refund_transaction.store',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ]
            ],
        ];
    }
}
