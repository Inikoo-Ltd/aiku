<?php

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\PurchaseOrder\ResolvePurchaseOrderDeliveryAddress;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPurchaseOrderData
{
    use AsObject;

    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $data = $purchaseOrder->data ?? [];
        $deliveryAddress = ResolvePurchaseOrderDeliveryAddress::run(
            $purchaseOrder->organisation,
            Arr::get($data, 'delivery_address')
        );

        $isParcel  = Arr::get($data, 'delivery_type') === 'parcel';
        $incoterms = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

        $blueprint = [
            [
                'title'  => __('Purchase order'),
                'fields' => [
                    'delivery_type' => [
                        'type'               => 'select',
                        'label'              => __('Delivery type'),
                        'information'        => __('Container: shipped by sea or road, with incoterm and ports. Parcel: sent by courier, so incoterm and ports are hidden.'),
                        'value'              => Arr::get($data, 'delivery_type'),
                        'revisit_after_save' => true,
                        'options'            => [
                            ['label' => __('Container'), 'value' => 'container'],
                            ['label' => __('Parcel'), 'value' => 'parcel'],
                        ],
                    ],
                    'parcel_reference' => [
                        'type'        => 'input',
                        'label'       => __('Parcel reference'),
                        'information' => __('The courier\'s tracking number for the parcel. Copied to the stock delivery when it is made.'),
                        'value'       => Arr::get($data, 'parcel_reference'),
                        'hidden'      => !$isParcel,
                    ],
                    'reference'     => [
                        'type'  => 'readonly',
                        'label' => __('Public Id'),
                        'value' => $purchaseOrder->reference,
                    ],
                ],
            ],
            [
                'title'  => __('Payment terms'),
                'fields' => [
                    'payment_terms' => [
                        'type'        => 'textarea',
                        'label'       => __('Payment terms'),
                        'information' => __('How and when the supplier is paid, e.g. 30% deposit and the balance before shipping. Printed on the purchase order.'),
                        'value'       => Arr::get($data, 'payment_terms'),
                    ],
                ],
            ],
            [
                'title'  => __('Delivery terms'),
                'fields' => [
                    'incoterm'         => [
                        'type'        => 'select',
                        'label'       => __('Incoterm'),
                        'information' => __('Who pays for the shipment and carries its risk up to which point, e.g. FOB: the supplier until the goods are loaded at the port of export. Printed on the purchase order.'),
                        'value'       => Arr::get($data, 'incoterm'),
                        'hidden'      => $isParcel,
                        'options'     => array_map(fn ($code) => ['label' => $code, 'value' => $code], $incoterms),
                    ],
                    'port_of_export'   => [
                        'type'        => 'input',
                        'label'       => __('Port of export'),
                        'information' => __('The port the goods leave from. Printed on the purchase order.'),
                        'value'       => Arr::get($data, 'port_of_export'),
                        'hidden'      => $isParcel,
                    ],
                    'port_of_import'   => [
                        'type'        => 'input',
                        'label'       => __('Port of import'),
                        'information' => __('The port the goods arrive at. Printed on the purchase order.'),
                        'value'       => Arr::get($data, 'port_of_import'),
                        'hidden'      => $isParcel,
                    ],
                    'delivery_address' => [
                        'type'        => 'textarea',
                        'label'       => __('Delivery address'),
                        'information' => __('The address the supplier ships the goods to, printed on the purchase order.'),
                        'value'       => $deliveryAddress,
                    ],
                ],
            ],
            [
                'title'  => __('Labels'),
                'fields' => [
                    'terms_and_conditions' => [
                        'type'        => 'editor_v2',
                        'label'       => __('Terms and conditions'),
                        'information' => __('The terms the supplier must follow, such as labelling and packing instructions. Printed at the end of the purchase order.'),
                        'value'       => Arr::get($data, 'terms_and_conditions'),
                        'full'        => true,
                    ],
                ],
            ],
        ];

        if (in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)) {
            array_splice($blueprint, 1, 0, [
                [
                    'title'  => __('Estimated process dates'),
                    'fields' => [
                        'estimated_production_date' => [
                            'type'        => 'date',
                            'label'       => __('Estimated production date'),
                            'information' => __('The day the supplier expects to finish making the goods. Used as the target for the Production step of the order journey.'),
                            'value'       => Arr::get($data, 'estimated_production_date'),
                        ],
                        'estimated_receiving_date'  => [
                            'type'        => 'date',
                            'label'       => __('Estimated receiving date'),
                            'information' => __('The day we expect the goods to arrive at our warehouse. Used as the target for the In transit step of the order journey.'),
                            'value'       => $purchaseOrder->estimatedReceivingDate(),
                        ],
                    ],
                ],
            ]);
        }

        return [
            'blueprint'   => $blueprint,
            'updateRoute' => [
                'method'     => 'patch',
                'name'       => 'grp.models.purchase-order.update',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ],
        ];
    }
}
