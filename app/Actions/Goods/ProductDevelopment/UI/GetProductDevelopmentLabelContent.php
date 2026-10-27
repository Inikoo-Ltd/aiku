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
        $allText    = [
            'product_name'   => $tradeUnit->name,
            'net_weight'     => $tradeUnit->net_weight ? $tradeUnit->net_weight.' g' : null,
            'net_volume'     => self::volumeLabel($tradeUnit->net_volume),
            'ingredients'    => $tradeUnit->ingredients()->orderBy('trade_unit_has_ingredients.position')->orderBy('trade_unit_has_ingredients.id')->pluck('name')->implode(', ') ?: null,
            'warnings'       => $tradeUnit->gpsr_warnings,
            'manufacturer'   => $tradeUnit->gpsr_manufacturer,
            'eu_responsible' => $tradeUnit->getEffectiveGpsrEuResponsible(),
            'ufi'            => $tradeUnit->ufi_number,
            'un_number'      => $tradeUnit->un_number,
            'origin'         => $tradeUnit->countryOrigin?->name,
        ];
        $packText = collect($allText)->map(fn (?string $value, string $key) => in_array($key, $onLabel, true) ? $value : null)->all();
        $labels   = self::fieldLabels();
        $lines    = collect($packText)->filter(fn (?string $value) => filled($value))->map(fn (string $value, string $key) => ['key' => $key, 'label' => $labels[$key], 'value' => $value])->values();
        $marks    = collect(TradeUnitLabelPresenceEnum::labels())->filter(fn ($label, $key) => data_get($labelInfo, $key) === true)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();

        return [
            'sku'        => ['code' => $tradeUnit->code, 'name' => $tradeUnit->name],
            'approved'   => $missing === [] && $compliance->isNotEmpty(),
            'missing'    => $missing,
            'text'       => $lines->all(),
            'label_text' => self::labelText($packText),
            'info'       => array_values(array_filter([
                ['key' => 'markets', 'label' => __('Markets'), 'value' => collect(data_get($labelInfo, 'markets', []))->map(fn ($market) => TradeUnitMarketEnum::labels()[$market] ?? $market)->implode(', ') ?: null],
                ['key' => 'languages', 'label' => __('Languages'), 'value' => collect(data_get($labelInfo, 'languages', []))->map(fn ($code) => strtoupper($code))->implode(', ') ?: null],
            ], fn (array $line) => filled($line['value']))),
            'marks'      => $marks,
            'pictograms' => $pictograms,
            'barcodes'   => [
                ['level' => 'unit', 'label' => __('Unit'), 'type' => 'EAN-13', 'value' => $tradeUnit->barcode ?: null, 'note' => null],
                ['level' => 'sko', 'label' => __('SKO (outer packing)'), 'type' => 'Code 128', 'value' => $stock?->barcode ?: null, 'note' => $stock ? null : __('No stock created for this SKU yet')],
                ['level' => 'carton', 'label' => __('Carton'), 'type' => 'Code 128', 'value' => $stock?->carton_barcode ?: null, 'note' => $stock ? null : __('No stock created for this SKU yet')],
            ],
            'values'     => [
                'text'       => [
                    'product_name'   => $packText['product_name'],
                    'ingredients'    => $packText['ingredients'],
                    'warnings'       => $packText['warnings'],
                    'manufacturer'   => $packText['manufacturer'],
                    'eu_responsible' => $packText['eu_responsible'],
                    'ufi'            => $packText['ufi'] ? 'UFI: '.$packText['ufi'] : null,
                    'un_number'      => $packText['un_number'] ? 'UN '.$packText['un_number'] : null,
                    'net_weight'     => $packText['net_weight'],
                    'net_volume'     => $packText['net_volume'],
                    'origin'         => $packText['origin'] ? __('Made in :country', ['country' => $packText['origin']]) : null,
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

    public const array LABEL_FIELDS = ['product_name', 'net_weight', 'net_volume', 'ingredients', 'warnings', 'manufacturer', 'eu_responsible', 'ufi', 'un_number', 'origin'];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'product_name'   => __('Product name'),
            'net_weight'     => __('Net weight'),
            'net_volume'     => __('Net volume'),
            'ingredients'    => __('Ingredients'),
            'warnings'       => __('Warnings & precautions'),
            'manufacturer'   => __('Manufacturer'),
            'eu_responsible' => __('EU responsible person'),
            'ufi'            => __('UFI'),
            'un_number'      => __('UN number'),
            'origin'         => __('Country of origin'),
        ];
    }

    /**
     * The pack text going on a SKU's label: every field except those compliance has left off.
     *
     * @return array<int, string>
     */
    public static function onLabel(ProductDevelopmentItem $item): array
    {
        return array_values(array_diff(self::LABEL_FIELDS, $item->label_excluded ?? []));
    }

    /**
     * "250 ml", or "1.5 L" from a litre up.
     */
    public static function volumeLabel(?int $millilitres): ?string
    {
        if (!$millilitres) {
            return null;
        }

        return $millilitres >= 1000 ? rtrim(rtrim(number_format($millilitres / 1000, 2, '.', ''), '0'), '.').' L' : $millilitres.' ml';
    }

    /**
     * The label text in reading order, ready to copy onto the pack.
     *
     * @param array<string, string|null> $packText
     */
    public static function labelText(array $packText): string
    {
        return collect([
            $packText['product_name'] ?? null,
            ($packText['net_weight'] ?? null) ? $packText['net_weight'] : null,
            ($packText['net_volume'] ?? null) ? $packText['net_volume'] : null,
            ($packText['ingredients'] ?? null) ? __('INGREDIENTS: :text', ['text' => $packText['ingredients']]) : null,
            ($packText['warnings'] ?? null) ? __('CAUTION: :text', ['text' => $packText['warnings']]) : null,
            ($packText['manufacturer'] ?? null) ? __('Manufacturer: :text', ['text' => $packText['manufacturer']]) : null,
            ($packText['eu_responsible'] ?? null) ? __('EU responsible person: :text', ['text' => $packText['eu_responsible']]) : null,
            ($packText['ufi'] ?? null) ? 'UFI: '.$packText['ufi'] : null,
            ($packText['un_number'] ?? null) ? 'UN '.$packText['un_number'] : null,
            ($packText['origin'] ?? null) ? __('Made in :country', ['country' => $packText['origin']]) : null,
        ])->filter()->implode("\n\n");
    }
}
