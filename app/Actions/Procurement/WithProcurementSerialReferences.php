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
            'format'          => $this->defaultSerialReference($parent, $model)->format,
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
        if (!$this->parentSerialReference($parent, $model) && $this->isNumberedBySupplier($parent, $model)) {
            $this->supplierSerialReference($parent)->save();
        }

        $serialReference = $this->parentSerialReference($parent, $model);
        $container       = $serialReference ? $parent : $parent->organisation;
        $suffix          = $this->organisationSuffix($parent, $model, $serialReference);

        do {
            $reference = GetSerialReference::run(container: $container, modelType: $model).$suffix;
        } while ($this->procurementReferenceIsUsed($parent, $model, $reference));

        return $reference;
    }

    private function nextProcurementReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): string
    {
        $parentSerialReference = $this->parentSerialReference($parent, $model);
        $serialReference       = $parentSerialReference ?? $this->defaultSerialReference($parent, $model);
        $suffix                = $this->organisationSuffix($parent, $model, $parentSerialReference);

        $serial = $serialReference->serial;
        do {
            $reference = sprintf($serialReference->format, ++$serial).$suffix;
        } while ($this->procurementReferenceIsUsed($parent, $model, $reference));

        return $reference;
    }

    /**
     * The agent sees every client's orders side by side, so an agent format that ends at the number (AWIndia%05d) gets the organisation code (AWIndia00095-ES).
     */
    private function organisationSuffix(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model, ?SerialReference $serialReference): string
    {
        if (!$parent instanceof OrgAgent || $model !== SerialReferenceModelEnum::PURCHASE_ORDER || !$serialReference || !preg_match('/%\d*d$/', $serialReference->format)) {
            return '';
        }

        return '-'.strtoupper($this->referenceSafe($parent->organisation->code));
    }

    private function procurementReferenceIsUsed(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model, string $reference): bool
    {
        if ($model === SerialReferenceModelEnum::STOCK_DELIVERY) {
            return StockDelivery::query()->where('organisation_id', $parent->organisation_id)->where('reference', $reference)->exists();
        }

        return PurchaseOrder::withTrashed()
            ->where('organisation_id', $parent->organisation_id)
            ->where(fn ($query) => $query->where('reference', $reference)->orWhere('agent_order_reference', $reference))
            ->exists();
    }

    private function parentSerialReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): ?SerialReference
    {
        if ($parent instanceof OrgPartner) {
            return null;
        }

        return $parent->serialReferences()->where('model', $model)->first();
    }

    private function defaultSerialReference(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): SerialReference
    {
        return $this->isNumberedBySupplier($parent, $model) ? $this->supplierSerialReference($parent) : $this->organisationSerialReference($parent, $model);
    }

    private function isNumberedBySupplier(OrgSupplier|OrgAgent|OrgPartner $parent, SerialReferenceModelEnum $model): bool
    {
        return $parent instanceof OrgSupplier && !$parent->org_agent_id && $model === SerialReferenceModelEnum::PURCHASE_ORDER;
    }

    /**
     * SUPPLIERCODE-0001, carrying on from the supplier's last Aurora number (BrowFarm-003 is followed by Browfarm-0004).
     */
    private function supplierSerialReference(OrgSupplier $orgSupplier): SerialReference
    {
        $auroraPurchaseOrders = $orgSupplier->purchaseOrders()->withTrashed()->whereNotNull('source_id');

        $lastAuroraReference = (clone $auroraPurchaseOrders)->orderByDesc('created_at')->orderByDesc('id')->value('reference');

        $lastAuroraNumber = preg_match('/(\d+)\D*$/', (string) $lastAuroraReference, $matches) ? (int) $matches[1] : null;

        $serial = $lastAuroraNumber !== null && $lastAuroraNumber <= 9999 ? $lastAuroraNumber : $auroraPurchaseOrders->count();

        return $orgSupplier->serialReferences()->make([
            'model'           => SerialReferenceModelEnum::PURCHASE_ORDER,
            'organisation_id' => $orgSupplier->organisation_id,
            'format'          => $this->referenceSafe($orgSupplier->supplier()->withTrashed()->value('code')).'-%04d',
            'serial'          => $serial,
        ]);
    }

    private function referenceSafe(?string $text): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $text);
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
                    'information'        => __('Put %04d where the number goes, e.g. :example. Only letters, numbers, - and _. Leave empty for the default numbering.', ['example' => $example]),
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
