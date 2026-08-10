<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 11:16:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\Artefact;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraArtefacts extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:artefacts {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Artefact
    {
        $artefactData = $organisationSource->fetchArtefact($organisationSourceId);


        return $this->fetchArtefact($artefactData);
    }


    public function fetchArtefact($artefactData): ?Artefact
    {
        if ($artefactData) {
            if ($artefact = Artefact::withTrashed()->where('source_id', $artefactData['artefact']['source_id'])
                ->first()) {
                try {
                    UpdateArtefact::make()->action(
                        artefact: $artefact,
                        modelData: $artefactData['artefact'],
                        hydratorsDelay: $this->hydratorsDelay
                    );
                } catch (Exception $e) {
                    $this->recordError($this->organisationSource, $e, $artefactData['artefact'], 'Artefact', 'update');

                    return null;
                }
            } else {
                try {
                    $artefact = StoreArtefact::make()->action(
                        production: $artefactData['production'],
                        modelData: $artefactData['artefact'],
                        hydratorsDelay: $this->hydratorsDelay
                    );
                } catch (Exception $e) {
                    $this->recordError($this->organisationSource, $e, $artefactData['artefact'], 'Artefact', 'store');

                    return null;
                }

                $sourceData = explode(':', $artefact->source_id);
                DB::connection('aurora')->table('Supplier Part Dimension')
                    ->where('Supplier Part Key', $sourceData[1])
                    ->update(['aiku_id' => $artefact->id]);
            }

            $this->fetchRecipe($artefact);

            return $artefact;
        }

        return null;
    }

    protected function fetchRecipe(Artefact $artefact): void
    {
        $sourceData = explode(':', $artefact->source_id);
        $bridgeRows = DB::connection('aurora')
            ->table('Production Part Raw Material Bridge')
            ->where('Production Part Raw Material Production Part Key', $sourceData[1])
            ->get();

        if ($bridgeRows->isEmpty()) {
            return;
        }

        $manufactureTask = $artefact->production->manufactureTasks()
            ->where('code', 'PROD')
            ->first();

        if (!$manufactureTask) {
            $manufactureTask = StoreManufactureTask::make()->action(
                production: $artefact->production,
                modelData: [
                    'code'                             => 'PROD',
                    'name'                             => 'Production',
                    'task_materials_cost'               => 0,
                    'task_energy_cost'                  => 0,
                    'task_other_cost'                   => 0,
                    'task_work_cost'                    => 0,
                    'task_lower_target'                 => 0,
                    'task_upper_target'                 => 0,
                    'operative_reward_terms'            => ManufactureTaskOperativeRewardTermsEnum::NEVER,
                    'operative_reward_allowance_type'   => ManufactureTaskOperativeRewardAllowanceTypeEnum::ON_TOP_SALARY,
                    'operative_reward_amount'           => 0,
                ]
            );
        }

        $artefact->manufactureTasks()->syncWithoutDetaching(
            [$manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]
        );

        $step = $artefact->manufactureTasks()
            ->wherePivot('manufacture_task_id', $manufactureTask->id)
            ->first()
            ?->pivot;

        if (!$step) {
            return;
        }

        foreach ($bridgeRows as $bridgeRow) {
            $rawMaterial = RawMaterial::where(
                'source_id',
                $artefact->organisation_id.':'.$bridgeRow->{'Production Part Raw Material Raw Material Key'}
            )->first();

            if (!$rawMaterial) {
                continue;
            }

            RecipeStepRawMaterial::updateOrCreate(
                [
                    'artefact_manufacture_task_id' => $step->id,
                    'raw_material_id'               => $rawMaterial->id,
                ],
                [
                    'quantity_per_unit' => $bridgeRow->{'Production Part Raw Material Ratio'},
                    'group_id'          => $artefact->group_id,
                    'organisation_id'   => $artefact->organisation_id,
                ]
            );
        }
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Supplier Part Dimension as spp')
            ->leftJoin('Part Dimension', 'Part SKU', 'Supplier Part Part SKU')
            ->leftJoin('Supplier Dimension as sd', 'Supplier Key', 'Supplier Part Supplier Key')
            ->select('Supplier Part Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('spp.aiku_id');
        }

        return $query->where('Supplier Part Status', ['Available', 'NoAvailable'])
            ->where('Part Status', '!=', 'Not In Use')
            ->where('spp.aiku_ignore', 'No')
            ->where('sd.Supplier Production', 'Yes')
            ->where('sd.Supplier Type', 'Free')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Supplier Part Dimension as spp')
            ->leftJoin('Part Dimension', 'Part SKU', 'Supplier Part Part SKU')
            ->leftJoin('Supplier Dimension as sd', 'Supplier Key', 'Supplier Part Supplier Key')
            ->select('Supplier Part Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('spp.aiku_id');
        }

        return $query->where('Supplier Part Status', ['Available', 'NoAvailable'])
            ->where('Part Status', '!=', 'Not In Use')
            ->where('spp.aiku_ignore', 'No')
            ->where('sd.Supplier Production', 'Yes')
            ->where('sd.Supplier Type', 'Free')
            ->count();
    }
}
