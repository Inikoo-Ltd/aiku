<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:48:15 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Json;

use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMiniDeliveryNoteShipments extends OrgAction
{
    use AsAction;
    use GetPlatformLogo;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        return $this->handle($deliveryNote);
    }

    public function jsonResponse(DeliveryNote $deliveryNote): array
    {
        $order     = $deliveryNote->orders->first();
        
        $additionalShipmentRoutes = [];

        if (!$deliveryNote->is_shipping_by_external) {
            if ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE) {
                $additionalShipmentRoutes = [
                    'get_external_shipment_route' => [
                        'label'      => __('Get shipment from Faire'),
                        'name'       => 'grp.models.delivery_note.shipment.store_faire',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ],
                ];
            } elseif ($order->platform->type == PlatformTypeEnum::TIKTOK) {
                $additionalShipmentRoutes = [
                    'get_external_shipment_route' => [
                        'label'      => __('Get shipment from Tiktok'),
                        'name'       => 'grp.models.delivery_note.shipment.store_tiktok',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ];
            }
        } else {
            $additionalShipmentRoutes = [
                'submit_route' => [
                    'name'       => 'grp.models.delivery_note.shipment.store',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ]
            ];
        }

        return [
            'shipment' => [
                'shipments'        => $deliveryNote->shipments ? ShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->toArray(request()) : [],
                'shipments_routes'           => [
                    ...$additionalShipmentRoutes,
                    'fetch_route' => [
                        'name'       => 'grp.json.shippers.index',
                        'parameters' => [
                            'organisation' => $deliveryNote->organisation->slug,
                        ]
                    ],
                    'delete_route' => [
                        'name'       => 'grp.models.delivery_note.shipment.detach',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ],
                ],
            ]
        ];
    }
}
