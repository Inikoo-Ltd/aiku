<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-23h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the designer needs to lay out a SKU's label: the pack text compliance has approved and marked
 * "Include on label", read from the trade unit, and the three barcodes (unit EAN-13, SKO and carton Code 128).
 */
class GetProductDevelopmentLabelContent
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): array
    {
        $tradeUnit  = $item->tradeUnit;
        $compliance = $productDevelopment->checklistItems()
            ->where('product_development_item_id', $item->id)
            ->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)
            ->get();
        $missing = $compliance->filter(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->isMissing())->pluck('label')->values()->all();

        /** @var Stock|null $stock */
        $stock      = $tradeUnit->stocks()->orderBy('stocks.id')->first();
        $labelInfo  = $tradeUnit->label_info ?? [];
        $pictograms = collect(ProductDevelopmentTargetFieldEnum::pictograms())->filter(fn (array $pictogram, string $key) => (bool) $tradeUnit->getAttribute($key))->values()->all();
        $onLabel    = self::onLabel($item);
        $packText   = collect([
            'ingredients'    => $tradeUnit->ingredients()->orderBy('name')->pluck('name')->implode(', ') ?: null,
            'warnings'       => $tradeUnit->gpsr_warnings,
            'manufacturer'   => $tradeUnit->gpsr_manufacturer,
            'eu_responsible' => $tradeUnit->getEffectiveGpsrEuResponsible(),
            'ufi'            => $tradeUnit->ufi_number,
            'un_number'      => $tradeUnit->un_number,
        ])->map(fn (?string $value, string $key) => in_array($key, $onLabel, true) ? $value : null)->all();

        return [
            'sku'        => ['code' => $tradeUnit->code, 'name' => $tradeUnit->name],
            'approved'   => $missing === [] && $compliance->isNotEmpty(),
            'missing'    => $missing,
            'text'       => array_values(array_filter([
                ['key' => 'product_name', 'label' => __('Product name'), 'value' => $tradeUnit->name],
                ['key' => 'ingredients', 'label' => __('Ingredients'), 'value' => $packText['ingredients']],
                ['key' => 'warnings', 'label' => __('Warnings & precautions'), 'value' => $packText['warnings']],
                ['key' => 'manufacturer', 'label' => __('Manufacturer'), 'value' => $packText['manufacturer']],
                ['key' => 'eu_responsible', 'label' => __('EU responsible person'), 'value' => $packText['eu_responsible']],
                ['key' => 'ufi', 'label' => __('UFI'), 'value' => $packText['ufi']],
                ['key' => 'un_number', 'label' => __('UN number'), 'value' => $packText['un_number']],
                ['key' => 'markets', 'label' => __('Markets'), 'value' => collect(data_get($labelInfo, 'markets', []))->map(fn ($market) => TradeUnitMarketEnum::labels()[$market] ?? $market)->implode(', ') ?: null],
                ['key' => 'languages', 'label' => __('Languages'), 'value' => collect(data_get($labelInfo, 'languages', []))->map(fn ($code) => strtoupper($code))->implode(', ') ?: null],
                ['key' => 'marks', 'label' => __('Marks on the label'), 'value' => collect(TradeUnitLabelPresenceEnum::labels())->filter(fn ($label, $key) => data_get($labelInfo, $key) === true)->implode(', ') ?: null],
            ], fn (array $line) => filled($line['value']))),
            'pictograms' => $pictograms,
            'barcodes'   => [
                ['level' => 'unit', 'label' => __('Unit EAN-13'), 'value' => $tradeUnit->barcode ?: null, 'note' => null],
                ['level' => 'sko', 'label' => __('SKO (outer packing, Code 128)'), 'value' => $stock?->barcode ?: null, 'note' => $stock ? null : __('No stock created for this SKU yet')],
                ['level' => 'carton', 'label' => __('Carton (Code 128)'), 'value' => $stock?->carton_barcode ?: null, 'note' => $stock ? null : __('No stock created for this SKU yet')],
            ],
            'values'     => [
                'text'       => [
                    'product_name'   => $tradeUnit->name,
                    'ingredients'    => $packText['ingredients'],
                    'warnings'       => $packText['warnings'],
                    'manufacturer'   => $packText['manufacturer'],
                    'eu_responsible' => $packText['eu_responsible'],
                    'ufi'            => $packText['ufi'] ? 'UFI: '.$packText['ufi'] : null,
                    'un_number'      => $packText['un_number'] ? 'UN '.$packText['un_number'] : null,
                    'net_weight'     => $tradeUnit->net_weight ? $tradeUnit->net_weight.' g' : null,
                    'origin'         => $tradeUnit->countryOrigin?->name ? __("Made in :country", ["country" => $tradeUnit->countryOrigin->name]) : null,
                ],
                'pictograms' => collect(ProductDevelopmentTargetFieldEnum::pictograms())->map(fn (array $pictogram, string $key) => (bool) $tradeUnit->getAttribute($key))->all(),
                'barcodes'   => [
                    'unit'   => $tradeUnit->barcode ?: null,
                    'sko'    => $stock?->barcode ?: null,
                    'carton' => $stock?->carton_barcode ?: null,
                ],
            ],
            'compliance_route' => ['name' => 'grp.product_developments.show', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'tab' => 'compliance']],
        ];
    }

    /**
     * The pack text fields marked "Include on label" for a SKU. Until compliance has ticked any, all of them.
     *
     * @return array<int, string>
     */
    public static function onLabel(ProductDevelopmentItem $item): array
    {
        return $item->label_fields ?? ProductDevelopmentTargetFieldEnum::labelFields();
    }
}
