<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial;

use App\Actions\OrgAction;
use App\Actions\Production\RawMaterial\UI\IndexRawMaterials;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Exports\Production\RawMaterialsExport;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRawMaterials extends OrgAction
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function rows(Production $production, ?string $search = null): array
    {
        return RawMaterial::query()
            ->where('raw_materials.production_id', $production->id)
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $terms = explode(',', $search);
                $query->whereStartWith('raw_materials.code', $terms)
                    ->orWhereAnyWordStartWith('raw_materials.description', $terms);
            }))
            ->leftJoin('org_stocks', 'org_stocks.id', 'raw_materials.org_stock_id')
            ->select([
                'raw_materials.code',
                'raw_materials.description',
                'raw_materials.unit',
                'raw_materials.unit_cost',
                'raw_materials.state',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.quantity_in_locations',
            ])
            ->selectSub(
                RecipeStepRawMaterial::query()
                    ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'recipe_step_raw_materials.artefact_manufacture_task_id')
                    ->whereColumn('recipe_step_raw_materials.raw_material_id', 'raw_materials.id')
                    ->selectRaw('count(distinct artefacts_manufacture_tasks.artefact_id)'),
                'number_artefacts'
            )
            ->orderBy('raw_materials.code')
            ->get()
            ->map(fn (RawMaterial $rawMaterial) => [
                $rawMaterial->code,
                $rawMaterial->description,
                $rawMaterial->unit ? RawMaterialUnitEnum::labels()[$rawMaterial->unit->value] : null,
                $rawMaterial->org_stock_code,
                $rawMaterial->org_stock_name,
                $rawMaterial->unit_cost === null ? null : (float) $rawMaterial->unit_cost,
                $rawMaterial->quantity_in_locations === null ? null : (float) $rawMaterial->quantity_in_locations,
                (int) $rawMaterial->number_artefacts,
                $rawMaterial->state ? RawMaterialStateEnum::labels()[$rawMaterial->state->value] : null,
            ])
            ->all();
    }

    public function handle(Production $production, array $modelData): BinaryFileResponse
    {
        $currency = $production->organisation->currency?->code ?? '';

        return Excel::download(
            new RawMaterialsExport($this->rows($production, Arr::get($modelData, 'search')), $currency),
            sprintf('raw-materials-%s-%s.xlsx', $production->slug, now()->toDateString())
        );
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return IndexRawMaterials::canSeeProductionRawMaterials($request->user(), $this->production);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
