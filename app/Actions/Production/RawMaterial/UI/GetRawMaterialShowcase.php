<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\UI;

use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRawMaterialShowcase
{
    use AsObject;

    public function handle(RawMaterial $rawMaterial): array
    {
        $rawMaterial->loadMissing(['organisation.currency', 'production', 'tradeUnit', 'orgStock', 'artefact']);

        $state       = $rawMaterial->state?->value;
        $stockStatus = $rawMaterial->stock_status?->value;
        $type        = $rawMaterial->type?->value;
        $unit        = $rawMaterial->unit?->value;

        $usedIn = $this->getUsedIn($rawMaterial);

        return [
            'code'                 => $rawMaterial->code,
            'description'          => $rawMaterial->description,
            'state'                => $state,
            'state_label'          => $state ? RawMaterialStateEnum::labels()[$state] : null,
            'state_icon'           => $state ? RawMaterialStateEnum::stateIcon()[$state] : null,
            'type_label'           => $type ? RawMaterialTypeEnum::labels()[$type] : null,
            'unit_label'           => $unit ? RawMaterialUnitEnum::labels()[$unit] : null,
            'unit_cost'            => $rawMaterial->unit_cost,
            'currency_code'        => $rawMaterial->organisation->currency?->code,
            'quantity_on_location' => $rawMaterial->quantity_on_location,
            'stock_status'         => $stockStatus,
            'stock_status_label'   => $stockStatus ? RawMaterialStockStatusEnum::labels()[$stockStatus] : null,
            'stock_status_icon'    => $stockStatus ? RawMaterialStockStatusEnum::stockStatusIcon()[$stockStatus] : null,
            'production'           => $rawMaterial->production ? [
                'slug' => $rawMaterial->production->slug,
                'code' => $rawMaterial->production->code,
                'name' => $rawMaterial->production->name,
            ] : null,
            'trade_unit'           => $rawMaterial->tradeUnit ? [
                'id'   => $rawMaterial->tradeUnit->id,
                'code' => $rawMaterial->tradeUnit->code,
                'name' => $rawMaterial->tradeUnit->name,
            ] : null,
            'org_stock'            => $rawMaterial->orgStock ? [
                'id'                    => $rawMaterial->orgStock->id,
                'code'                  => $rawMaterial->orgStock->code,
                'name'                  => $rawMaterial->orgStock->name,
                'quantity_in_locations' => (float) $rawMaterial->orgStock->quantity_in_locations,
            ] : null,
            'artefact'             => $rawMaterial->artefact ? [
                'id'   => $rawMaterial->artefact->id,
                'slug' => $rawMaterial->artefact->slug,
                'code' => $rawMaterial->artefact->code,
                'name' => $rawMaterial->artefact->name,
            ] : null,
            'number_artefacts'     => $usedIn->pluck('artefact_id')->unique()->count(),
            'used_in'              => $usedIn->values()->all(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{artefact_id: int, artefact_slug: string, artefact_code: string, artefact_name: string, task_code: string|null, task_name: string|null, position: int|null, quantity_per_unit: string}>
     */
    private function getUsedIn(RawMaterial $rawMaterial): \Illuminate\Support\Collection
    {
        return RecipeStepRawMaterial::where('raw_material_id', $rawMaterial->id)
            ->with(['recipeStep.artefact', 'recipeStep.manufactureTask'])
            ->get()
            ->filter(fn (RecipeStepRawMaterial $step) => $step->recipeStep?->artefact)
            ->map(fn (RecipeStepRawMaterial $step) => [
                'artefact_id'       => $step->recipeStep->artefact->id,
                'artefact_slug'     => $step->recipeStep->artefact->slug,
                'artefact_code'     => $step->recipeStep->artefact->code,
                'artefact_name'     => $step->recipeStep->artefact->name,
                'task_code'         => $step->recipeStep->manufactureTask?->code,
                'task_name'         => $step->recipeStep->manufactureTask?->name,
                'position'          => $step->recipeStep->position,
                'quantity_per_unit' => $step->quantity_per_unit,
            ])
            ->sortBy(['artefact_code', 'position']);
    }
}
