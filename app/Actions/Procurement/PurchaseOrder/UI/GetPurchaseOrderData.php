<?php

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPurchaseOrderData
{
    use AsObject;

    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $data = $purchaseOrder->data ?? [];

        $isParcel  = Arr::get($data, 'delivery_type') === 'parcel';
        $incoterms = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

        $blueprint = [
            [
                'title'  => __('Purchase order'),
                'fields' => [
                    'delivery_type' => [
                        'type'               => 'select',
                        'label'              => __('Delivery type'),
                        'value'              => Arr::get($data, 'delivery_type'),
                        'revisit_after_save' => true,
                        'options'            => [
                            ['label' => __('Container'), 'value' => 'container'],
                            ['label' => __('Parcel'), 'value' => 'parcel'],
                        ],
                    ],
                    'reference'     => [
                        'type'  => 'readonly',
                        'label' => __('Public Id'),
                        'value' => $purchaseOrder->reference,
                    ],
                ],
            ],
            [
                'title'  => __('Estimated process dates'),
                'fields' => [
                    'estimated_production_date' => [
                        'type'  => 'date',
                        'label' => __('Estimated production date'),
                        'value' => Arr::get($data, 'estimated_production_date'),
                    ],
                    'estimated_receiving_date'  => [
                        'type'  => 'date',
                        'label' => __('Estimated receiving date'),
                        'value' => Arr::get($data, 'estimated_receiving_date'),
                    ],
                ],
            ],
            [
                'title'  => __('Payment terms'),
                'fields' => [
                    'payment_terms' => [
                        'type'  => 'textarea',
                        'label' => __('Payment terms'),
                        'value' => Arr::get($data, 'payment_terms'),
                    ],
                ],
            ],
            [
                'title'  => __('Delivery terms'),
                'fields' => [
                    'incoterm'         => [
                        'type'    => 'select',
                        'label'   => __('Incoterm'),
                        'value'   => Arr::get($data, 'incoterm'),
                        'hidden'  => $isParcel,
                        'options' => array_map(fn ($code) => ['label' => $code, 'value' => $code], $incoterms),
                    ],
                    'port_of_export'   => [
                        'type'   => 'input',
                        'label'  => __('Port of export'),
                        'value'  => Arr::get($data, 'port_of_export'),
                        'hidden' => $isParcel,
                    ],
                    'port_of_import'   => [
                        'type'   => 'input',
                        'label'  => __('Port of import'),
                        'value'  => Arr::get($data, 'port_of_import'),
                        'hidden' => $isParcel,
                    ],
                    'delivery_address' => [
                        'type'  => 'textarea',
                        'label' => __('Delivery address'),
                        'value' => Arr::get($data, 'delivery_address'),
                    ],
                ],
            ],
            [
                'title'  => __('Labels'),
                'fields' => [
                    'terms_and_conditions' => [
                        'type'  => 'editor_v2',
                        'label' => __('Terms and conditions'),
                        'value' => Arr::get($data, 'terms_and_conditions'),
                        'full'  => true,
                    ],
                ],
            ],
        ];

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
