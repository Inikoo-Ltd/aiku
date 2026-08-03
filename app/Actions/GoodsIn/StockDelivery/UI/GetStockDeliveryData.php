<?php

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockDeliveryData
{
    use AsObject;

    public function handle(StockDelivery $stockDelivery): array
    {
        $data = $stockDelivery->data ?? [];

        $isParcel  = Arr::get($data, 'delivery_type') === 'parcel';
        $incoterms = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

        $blueprint = [
            [
                'title'  => __('Stock delivery'),
                'fields' => [
                    'delivery_type' => [
                        'type'               => 'select',
                        'label'              => __('Stock delivery type'),
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
                        'value' => $stockDelivery->reference,
                    ],
                ],
            ],
            [
                'title'  => __('Invoice'),
                'fields' => [
                    'invoice_number' => [
                        'type'  => 'input',
                        'label' => __('Invoice number'),
                        'value' => Arr::get($data, 'invoice_number'),
                    ],
                    'invoice_date'   => [
                        'type'  => 'date',
                        'label' => __('Invoice date'),
                        'value' => Arr::get($data, 'invoice_date'),
                    ],
                ],
            ],
            [
                'title'  => __('Estimated dates'),
                'fields' => [
                    'estimated_dispatched_date' => [
                        'type'  => 'date',
                        'label' => __('Estimated dispatched date'),
                        'value' => Arr::get($data, 'estimated_dispatched_date'),
                    ],
                    'estimated_receiving_date'  => [
                        'type'  => 'date',
                        'label' => __('Estimated receiving date'),
                        'value' => Arr::get($data, 'estimated_receiving_date'),
                    ],
                ],
            ],
            [
                'title'  => __('Delivery rules'),
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
        ];

        return [
            'blueprint'   => $blueprint,
            'updateRoute' => [
                'method'     => 'patch',
                'name'       => 'grp.models.stock-delivery.update',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
        ];
    }
}
