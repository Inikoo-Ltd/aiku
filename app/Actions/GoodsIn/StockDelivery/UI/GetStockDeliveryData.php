<?php

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockDeliveryData
{
    use AsObject;

    public function handle(StockDelivery $stockDelivery): array
    {
        $data = $stockDelivery->data ?? [];

        $deliveryType = StockDeliveryTypeEnum::tryFrom((string) Arr::get($data, 'delivery_type'));
        $isParcel     = $deliveryType === StockDeliveryTypeEnum::PARCEL;
        $incoterms = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

        $purchaseOrderIncoterms = $this->purchaseOrderIncoterms($stockDelivery, $incoterms);

        $blueprint = [
            [
                'title'  => __('Stock delivery'),
                'fields' => [
                    'reference'     => [
                        'type'     => 'input',
                        'label'    => __('Public Id'),
                        'required' => true,
                        'value'    => $stockDelivery->reference,
                    ],
                    'delivery_type' => [
                        'type'               => 'select',
                        'label'              => __('How it ships'),
                        'required'           => true,
                        'value'              => Arr::get($data, 'delivery_type'),
                        'revisit_after_save' => true,
                        'options'            => StockDeliveryTypeEnum::selectOptions(),
                    ],
                    'container_size' => [
                        'type'    => 'select',
                        'label'    => __('Container size'),
                        'required' => $deliveryType === StockDeliveryTypeEnum::CONTAINER,
                        'value'    => Arr::get($data, 'container_size'),
                        'hidden'  => $deliveryType !== null && $deliveryType !== StockDeliveryTypeEnum::CONTAINER,
                        'options' => OrgAgentOrderContainerEnum::selectOptions(),
                    ],
                    'hide_cbm'       => [
                        'type'        => 'toggle',
                        'label'       => __('Hide CBM'),
                        'information' => __('The PDF and CSV of this container leave the CBM out'),
                        'value'       => (bool) Arr::get($data, 'hide_cbm', false),
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
                'title'  => __('Tracking'),
                'fields' => [
                    'shipper_reference' => [
                        'type'  => 'input',
                        'label' => __('Shipper reference'),
                        'value' => Arr::get($data, 'shipper_reference'),
                    ],
                    'tracking_number'   => [
                        'type'  => 'input',
                        'label' => match ($deliveryType) {
                            StockDeliveryTypeEnum::AIR    => __('Air waybill number'),
                            StockDeliveryTypeEnum::PARCEL => __('Tracking number'),
                            default                       => __('Bill of lading number'),
                        },
                        'value' => Arr::get($data, 'tracking_number'),
                    ],
                    'container_number'  => [
                        'type'   => 'input',
                        'label'  => __('Container number'),
                        'value'  => Arr::get($data, 'container_number'),
                        'hidden' => in_array($deliveryType, [StockDeliveryTypeEnum::AIR, StockDeliveryTypeEnum::PARCEL]),
                    ],
                    'shipping_agent'    => [
                        'type'  => 'input',
                        'label' => __('Shipping agent'),
                        'value' => Arr::get($data, 'shipping_agent'),
                    ],
                ],
            ],
            [
                'title'  => __('Delivery rules'),
                'fields' => [
                    'incoterm'         => [
                        'type'        => 'select',
                        'label'       => __('Incoterm'),
                        'value'       => Arr::get($data, 'incoterm'),
                        'hidden'      => $isParcel,
                        'information' => $purchaseOrderIncoterms ? __('The purchase orders in this container suggest their incoterm, listed first; any other can be chosen.') : null,
                        'warning'     => $this->incotermWarning(Arr::get($data, 'incoterm'), $purchaseOrderIncoterms),
                        'options'     => $this->incotermOptions($incoterms, $purchaseOrderIncoterms),
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

    /**
     * @return array<string, list<string>> incoterm => references of the purchase orders in the container using it
     */
    private function purchaseOrderIncoterms(StockDelivery $stockDelivery, array $incoterms): array
    {
        return $stockDelivery->purchaseOrders()
            ->orderBy('purchase_orders.reference')
            ->get(['purchase_orders.id', 'purchase_orders.reference', 'purchase_orders.data'])
            ->groupBy(fn (PurchaseOrder $purchaseOrder) => strtoupper(trim((string) Arr::get($purchaseOrder->data, 'incoterm'))))
            ->filter(fn ($purchaseOrders, string $incoterm) => in_array($incoterm, $incoterms, true))
            ->map(fn ($purchaseOrders) => $purchaseOrders->pluck('reference')->all())
            ->all();
    }

    private function incotermOptions(array $incoterms, array $purchaseOrderIncoterms): array
    {
        $suggested = array_map(fn (string $code) => [
            'label' => __(':incoterm · from :purchase_orders', [
                'incoterm'        => $code,
                'purchase_orders' => count($purchaseOrderIncoterms[$code]) > 2
                    ? __(':count purchase orders', ['count' => count($purchaseOrderIncoterms[$code])])
                    : implode(', ', $purchaseOrderIncoterms[$code]),
            ]),
            'value' => $code,
        ], array_keys($purchaseOrderIncoterms));

        $others = array_map(
            fn (string $code) => ['label' => $code, 'value' => $code],
            array_values(array_diff($incoterms, array_keys($purchaseOrderIncoterms)))
        );

        return [...$suggested, ...$others];
    }

    private function incotermWarning(?string $incoterm, array $purchaseOrderIncoterms): ?string
    {
        if (count($purchaseOrderIncoterms) > 1) {
            return __('The purchase orders in this container have different incoterms: :incoterms', [
                'incoterms' => implode(', ', array_keys($purchaseOrderIncoterms)),
            ]);
        }

        if (filled($incoterm) && $purchaseOrderIncoterms && !isset($purchaseOrderIncoterms[$incoterm])) {
            return __('The purchase orders in this container are :incoterm', ['incoterm' => array_key_first($purchaseOrderIncoterms)]);
        }

        return null;
    }
}
