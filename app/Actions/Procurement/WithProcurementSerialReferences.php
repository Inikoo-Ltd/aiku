<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 14:10:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\SerialReference;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;

trait WithProcurementSerialReferences
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function procurementSerialReferenceRules(): array
    {
        $rules = [];
        foreach ([SerialReferenceModelEnum::PURCHASE_ORDER, SerialReferenceModelEnum::STOCK_DELIVERY] as $model) {
            $rules[$model->value.'_reference_format'] = ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]*%\d*d[A-Za-z0-9_-]*$/'];
            $rules[$model->value.'_last_number']      = ['sometimes', 'required', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    protected function updateProcurementSerialReferences(OrgSupplier|OrgAgent $parent, array $modelData): array
    {
        foreach ([SerialReferenceModelEnum::PURCHASE_ORDER, SerialReferenceModelEnum::STOCK_DELIVERY] as $model) {
            $modelData = $this->updateProcurementSerialReference($parent, $model, $modelData);
        }

        return $modelData;
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    private function updateProcurementSerialReference(OrgSupplier|OrgAgent $parent, SerialReferenceModelEnum $model, array $modelData): array
    {
        $formatField     = $model->value.'_reference_format';
        $lastNumberField = $model->value.'_last_number';
        $hasFormat       = Arr::has($modelData, $formatField);
        $hasLastNumber   = Arr::has($modelData, $lastNumberField);
        $format          = Arr::pull($modelData, $formatField);
        $lastNumber      = Arr::pull($modelData, $lastNumberField);

        if ($hasFormat && !$format) {
            $parent->serialReferences()->where('model', $model)->delete();

            return $modelData;
        }

        if (!$hasFormat && !$hasLastNumber) {
            return $modelData;
        }

        $serialReference = $this->parentSerialReference($parent, $model) ?? $parent->serialReferences()->make([
            'model'           => $model,
            'organisation_id' => $parent->organisation_id,
            'format'          => $this->organisationSerialReference($parent, $model)->format,
        ]);

        if ($hasFormat) {
            $serialReference->format = $format;
        }
        if ($hasLastNumber) {
            $serialReference->serial = $lastNumber;
        }
        $serialReference->save();

        return $modelData;
    }

    public function nextPurchaseOrderReference(OrgSupplier|OrgAgent|OrgPartner $parent): string
    {
        return $this->nextProcurementReference($parent, SerialReferenceModelEnum::PURCHASE_ORDER);
    }

    public function nextStockDeliveryReference(OrgSupplier|OrgAgent|OrgPartner $parent): string
    {
        return $this->nextProcurementReference($parent, SerialReferenceModelEnum::STOCK_DELIVERY);
    }

    public function newProcurementReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): string
    {
        $container = $this->parentSerialReference($parent, $model) ? $parent : $parent->organisation;

        do {
            $reference = GetSerialReference::run(container: $container, modelType: $model);
        } while ($this->procurementReferenceIsUsed($parent, $model, $reference));

        return $reference;
    }

    private function nextProcurementReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): string
    {
        $serialReference = $this->parentSerialReference($parent, $model) ?? $this->organisationSerialReference($parent, $model);

        $serial = $serialReference->serial;
        do {
            $reference = sprintf($serialReference->format, ++$serial);
        } while ($this->procurementReferenceIsUsed($parent, $model, $reference));

        return $reference;
    }

    private function procurementReferenceIsUsed(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model, string $reference): bool
    {
        $query = $model === SerialReferenceModelEnum::STOCK_DELIVERY ? StockDelivery::query() : PurchaseOrder::query();

        return $query->where('organisation_id', $parent->organisation_id)->where('reference', $reference)->exists();
    }

    private function parentSerialReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): ?SerialReference
    {
        if ($parent instanceof OrgPartner) {
            return null;
        }

        return $parent->serialReferences()->where('model', $model)->first();
    }

    private function organisationSerialReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): SerialReference
    {
        return $parent->organisation->serialReferences()->where('model', $model)->firstOrFail();
    }

    /**
     * @param  array{name: string, parameters: mixed}  $updateRoute
     * @return array<int, array<string, mixed>>
     */
    protected function procurementSerialReferenceSections(OrgSupplier|OrgAgent $parent, array $updateRoute): array
    {
        return [
            $this->procurementSerialReferenceSection(
                $parent,
                SerialReferenceModelEnum::PURCHASE_ORDER,
                $updateRoute,
                __('Purchase order numbers'),
                __('Order number format'),
                __('Last order number'),
                __('Next order number'),
                'Camacho-%04d_UK'
            ),
            $this->procurementSerialReferenceSection(
                $parent,
                SerialReferenceModelEnum::STOCK_DELIVERY,
                $updateRoute,
                __('Stock delivery numbers'),
                __('Delivery number format'),
                __('Last delivery number'),
                __('Next delivery number'),
                'Camacho-D%04d_UK'
            ),
        ];
    }

    /**
     * @param  array{name: string, parameters: mixed}  $updateRoute
     * @return array<string, mixed>
     */
    private function procurementSerialReferenceSection(
        OrgSupplier|OrgAgent $parent,
        SerialReferenceModelEnum $model,
        array $updateRoute,
        string $title,
        string $formatLabel,
        string $lastNumberLabel,
        string $nextLabel,
        string $example
    ): array {
        $serialReference = $this->parentSerialReference($parent, $model);

        return [
            'label'  => $title,
            'title'  => $title,
            'icon'   => 'fal fa-hashtag',
            'fields' => [
                $model->value.'_reference_format' => [
                    'type'               => 'input',
                    'label'              => $formatLabel,
                    'information'        => __('Put %04d where the number goes, e.g. :example. Only letters, numbers, - and _. Leave empty to use the organisation numbers.', ['example' => $example]),
                    'value'              => $serialReference?->format,
                    'updateRoute'        => $updateRoute,
                    'revisit_after_save' => true,
                ],
                $model->value.'_last_number' => [
                    'type'               => 'input',
                    'label'              => $lastNumberLabel,
                    'information'        => $nextLabel.': '.$this->nextProcurementReference($parent, $model),
                    'value'              => $serialReference?->serial,
                    'options'            => ['inputType' => 'number'],
                    'updateRoute'        => $updateRoute,
                    'revisit_after_save' => true,
                ],
            ],
        ];
    }
}
