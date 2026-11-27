<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Goods\TradeUnit\ShareTradeUnitDocuments;
use App\Actions\Production\Artefact\BranchArtefact;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentCopySectionEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\Ingredient;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CopyTradeUnitInformation
{
    use AsAction;

    /**
     * Gives a new SKU the information of the product it replaces (HELP-4782: same product, new packaging),
     * and remembers where it came from so the copied compliance can be reviewed.
     *
     * @param  array<int, ProductDevelopmentCopySectionEnum>  $sections
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, TradeUnit $source, array $sections): ProductDevelopmentItem
    {
        $sections  = array_values(array_filter($sections, fn (ProductDevelopmentCopySectionEnum $section) => in_array($section, ProductDevelopmentCopySectionEnum::forKind($productDevelopment->kind), true)));
        $tradeUnit = $item->tradeUnit;

        $attributes = [];
        foreach ($sections as $section) {
            foreach ($section->columns() as $column) {
                $attributes[$column] = $source->getAttribute($column);
            }
        }

        $compliance = $tradeUnit->compliance ?? [];
        if (in_array(ProductDevelopmentCopySectionEnum::COMPLIANCE, $sections, true)) {
            $compliance = array_merge($compliance, Arr::except($source->compliance ?? [], ['material_composition']));
        }
        if (in_array(ProductDevelopmentCopySectionEnum::INGREDIENTS, $sections, true) && Arr::has($source->compliance ?? [], 'material_composition')) {
            $compliance['material_composition'] = $source->compliance['material_composition'];
        }
        if ($compliance !== ($tradeUnit->compliance ?? [])) {
            $attributes['compliance'] = $compliance;
        }

        if (!$productDevelopment->trade_unit_family_id && !$tradeUnit->trade_unit_family_id && $source->trade_unit_family_id) {
            $attributes['trade_unit_family_id'] = $source->trade_unit_family_id;
        }

        if ($attributes) {
            $tradeUnit->update($attributes);
        }

        if (in_array(ProductDevelopmentCopySectionEnum::INGREDIENTS, $sections, true)) {
            $this->copyIngredients($source, $tradeUnit);
        }

        if (in_array(ProductDevelopmentCopySectionEnum::COMPLIANCE, $sections, true)) {
            $this->shareDocuments($source, $tradeUnit);
        }

        if (in_array(ProductDevelopmentCopySectionEnum::FORMULATION, $sections, true)) {
            $this->copyFormulation($productDevelopment, $item, $source);
        }

        $item->update([
            'copied_from_trade_unit_id' => $source->id,
            'copied_sections'           => array_map(fn (ProductDevelopmentCopySectionEnum $section) => $section->value, $sections),
        ]);

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::INFORMATION_COPIED, [], [
            'sku'      => $tradeUnit->code,
            'source'   => $source->code,
            'sections' => array_map(fn (ProductDevelopmentCopySectionEnum $section) => $section->value, $sections),
        ]);

        return $item;
    }

    private function copyIngredients(TradeUnit $source, TradeUnit $tradeUnit): void
    {
        $tradeUnit->ingredients()->sync(
            $source->ingredients()->get()->mapWithKeys(fn (Ingredient $ingredient) => [
                $ingredient->id => Arr::except($ingredient->pivot->getAttributes(), ['id', 'trade_unit_id', 'ingredient_id', 'created_at', 'updated_at']),
            ])->all()
        );
    }

    private function shareDocuments(TradeUnit $source, TradeUnit $tradeUnit): void
    {
        $documentsSource = $source->documents_trade_unit_id ? TradeUnit::find($source->documents_trade_unit_id) : $source;
        if (!$documentsSource || $documentsSource->id === $tradeUnit->id || $tradeUnit->documents_trade_unit_id || !$documentsSource->attachments()->exists()) {
            return;
        }

        ShareTradeUnitDocuments::make()->handle($documentsSource, ['trade_units' => [$tradeUnit->id]]);
    }

    private function copyFormulation(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, TradeUnit $source): void
    {
        $artefact = $item->artefact ?? ProductDevelopmentRecipes::linkArtefact($productDevelopment, $item);
        if (!$artefact || !$productDevelopment->production) {
            return;
        }

        $sourceArtefact = $productDevelopment->production->artefacts()
            ->where('trade_unit_id', $source->id)
            ->whereIn('state', ArtefactStateEnum::launched())
            ->orderBy('id')
            ->first();
        if (!$sourceArtefact) {
            return;
        }

        BranchArtefact::copyRecipe($sourceArtefact, $artefact);

        $artefact->update(array_filter([
            'recommended_batch_size' => $artefact->recommended_batch_size ?? $sourceArtefact->recommended_batch_size,
            'shelf_life_days'        => $artefact->shelf_life_days ?? $sourceArtefact->shelf_life_days,
        ], fn ($value) => $value !== null));
    }
}
