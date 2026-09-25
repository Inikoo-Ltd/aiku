<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 14:10:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Helpers\SerialReference;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Support\Arr;

trait WithPurchaseOrderSerialReference
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function purchaseOrderSerialReferenceRules(): array
    {
        return [
            'purchase_order_reference_format' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[^%]*%\d*d[^%]*$/'],
            'purchase_order_last_number'      => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    protected function updatePurchaseOrderSerialReference(OrgSupplier|OrgAgent $parent, array $modelData): array
    {
        $hasFormat     = Arr::has($modelData, 'purchase_order_reference_format');
        $hasLastNumber = Arr::has($modelData, 'purchase_order_last_number');
        $format        = Arr::pull($modelData, 'purchase_order_reference_format');
        $lastNumber    = Arr::pull($modelData, 'purchase_order_last_number');

        if ($hasFormat && !$format) {
            $parent->purchaseOrderSerialReference()->delete();

            return $modelData;
        }

        if (!$hasFormat && !$hasLastNumber) {
            return $modelData;
        }

        $serialReference = $parent->purchaseOrderSerialReference ?? $parent->serialReferences()->make([
            'model'           => SerialReferenceModelEnum::PURCHASE_ORDER,
            'organisation_id' => $parent->organisation_id,
            'format'          => $this->organisationPurchaseOrderSerialReference($parent)->format,
        ]);

        if ($hasFormat) {
            $serialReference->format = $format;
        }
        if ($hasLastNumber) {
            $serialReference->serial = $lastNumber;
        }
        $serialReference->save();
        $parent->unsetRelation('purchaseOrderSerialReference');

        return $modelData;
    }

    public function nextPurchaseOrderReference(OrgSupplier|OrgAgent|OrgPartner $parent): string
    {
        $serialReference = $parent instanceof OrgPartner ? null : $parent->purchaseOrderSerialReference;
        $serialReference ??= $this->organisationPurchaseOrderSerialReference($parent);

        $serial = $serialReference->serial;
        do {
            $reference = sprintf($serialReference->format, ++$serial);
        } while ($parent->organisation->purchaseOrders()->where('reference', $reference)->exists());

        return $reference;
    }

    private function organisationPurchaseOrderSerialReference(OrgSupplier|OrgAgent|OrgPartner $parent): SerialReference
    {
        return $parent->organisation->serialReferences()->where('model', SerialReferenceModelEnum::PURCHASE_ORDER)->firstOrFail();
    }

    /**
     * @param  array{name: string, parameters: mixed}  $updateRoute
     * @return array<string, mixed>
     */
    protected function purchaseOrderSerialReferenceSection(OrgSupplier|OrgAgent $parent, array $updateRoute): array
    {
        $serialReference = $parent->purchaseOrderSerialReference;

        return [
            'title'  => __('Purchase order numbers'),
            'icon'   => 'fal fa-hashtag',
            'fields' => [
                'purchase_order_reference_format' => [
                    'type'        => 'input',
                    'label'       => __('Order number format'),
                    'information' => __('Put %04d where the number goes, e.g. Camacho-%04d_UK. Leave empty to use the organisation numbers.'),
                    'value'       => $serialReference?->format,
                    'updateRoute'        => $updateRoute,
                    'revisit_after_save' => true,
                ],
                'purchase_order_last_number' => [
                    'type'        => 'input',
                    'label'       => __('Last order number'),
                    'information' => __('Next order number').': '.$this->nextPurchaseOrderReference($parent),
                    'value'       => $serialReference?->serial,
                    'options'     => ['inputType' => 'number'],
                    'updateRoute'        => $updateRoute,
                    'revisit_after_save' => true,
                ],
            ],
        ];
    }
}
