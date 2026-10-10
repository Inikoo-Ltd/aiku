<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Enums\Goods\Packaging\PackagingLevelEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\Goods\PackagingComponent;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the trade unit declares for GPSR, EUDR and PPWR: the supplier's v7 answers and its packaging family.
 */
class GetTradeUnitCompliance
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        $compliance = $tradeUnit->compliance ?? [];
        $family     = $tradeUnit->packagingFamily()->with('components')->first();

        $components = $family?->components->map(fn (PackagingComponent $component) => [
            'id'                        => $component->id,
            'level'                     => PackagingLevelEnum::labels()[$component->packaging_level->value],
            'name'                      => $component->name,
            'material'                  => $component->material,
            'material_id_code'          => $component->material_id_code,
            'material_category'         => PackagingMaterialCategoryEnum::labels()[$component->material_category->value],
            'weight_g'                  => $component->weight_g === null ? null : (float)$component->weight_g,
            'quantity'                  => (float)$component->pivot->quantity,
            'quantity_per_unit'         => (float)$component->pivot->quantity_per_unit,
            'weight_per_unit_g'         => $component->weight_g === null ? null : round((float)$component->weight_g * (float)$component->pivot->quantity_per_unit, 3),
            'recycled_content_pct'      => $component->recycled_content_pct === null ? null : (float)$component->recycled_content_pct,
            'recycled_content_evidence' => $component->recycled_content_evidence,
            'recyclability'             => $component->recyclability,
            'separable'                 => $component->separable,
            'marks'                     => $component->marks,
            'national_marks'            => $component->national_marks,
            'artwork_owner'             => $component->artwork_owner,
            'notes'                     => $component->notes,
        ])->sortBy(fn (array $component) => array_search($component['level'], PackagingLevelEnum::labels()))->values()->all() ?? [];

        return [
            'gpsr' => [
                ['label' => __('Manufacturer'), 'value' => $tradeUnit->gpsr_manufacturer],
                ['label' => __('EU responsible person'), 'value' => $tradeUnit->gpsr_eu_responsible],
                ['label' => __('Warnings'), 'value' => $tradeUnit->gpsr_warnings],
                ['label' => __('Instructions'), 'value' => $tradeUnit->gpsr_manual],
                ['label' => __('Languages'), 'value' => $tradeUnit->gpsr_class_languages],
                ['label' => __('Brand'), 'value' => Arr::get($compliance, 'brand')],
                ['label' => __('Batch traceability'), 'value' => Arr::get($compliance, 'batch_traceability')],
            ],
            'regulatory' => [
                ['label' => __('Regulatory category'), 'value' => Arr::get($compliance, 'regulatory_category')],
                ['label' => __('Toy status'), 'value' => Arr::get($compliance, 'toy_status')],
                ['label' => __('Batteries / magnets'), 'value' => Arr::get($compliance, 'batteries_magnets')],
                ['label' => __('SVHC above 0.1%'), 'value' => Arr::get($compliance, 'svhc')],
                ['label' => __('SVHC substance'), 'value' => Arr::get($compliance, 'svhc_substance')],
                ['label' => __('CLP signal word'), 'value' => Arr::get($compliance, 'clp_signal_word')],
            ],
            'material_composition' => Arr::get($compliance, 'material_composition.materials', []),
            'material_composition_text' => Arr::get($compliance, 'material_composition.text'),
            'eudr' => [
                ['label' => __('Status'), 'value' => Arr::get($compliance, 'eudr.status')],
                ['label' => __('Commodity'), 'value' => Arr::get($compliance, 'eudr.commodity')],
                ['label' => __('Species'), 'value' => Arr::get($compliance, 'eudr.species')],
                ['label' => __('Country of production'), 'value' => Arr::get($compliance, 'eudr.country')],
                ['label' => __('Region of production'), 'value' => Arr::get($compliance, 'eudr.region')],
                ['label' => __('Plot geolocation'), 'value' => Arr::get($compliance, 'eudr.geolocation')],
                ['label' => __('Certification'), 'value' => Arr::get($compliance, 'eudr.certification')],
                ['label' => __('Legality evidence'), 'value' => Arr::get($compliance, 'eudr.legality_evidence')],
            ],
            'packaging' => $family ? [
                'code'              => $family->code,
                'status'            => $family->status,
                'shared_with'       => $family->tradeUnits()->whereKeyNot($tradeUnit->id)->count(),
                'weight_per_unit_g' => round(collect($components)->sum('weight_per_unit_g'), 3),
                'components'        => $components,
            ] : null,
        ];
    }
}
