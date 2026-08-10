<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Models\Production\RawMaterial;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraRawMaterials extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:raw_materials {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?RawMaterial
    {
        $data = $organisationSource->fetchRawMaterial($organisationSourceId);
        if (!$data || empty($data['raw_material'])) {
            return null;
        }

        $modelData = $data['raw_material'];

        if ($rawMaterial = RawMaterial::withTrashed()->where('source_id', $modelData['source_id'])->first()) {
            try {
                $rawMaterial = UpdateRawMaterial::make()->action(
                    rawMaterial: $rawMaterial,
                    modelData: $modelData
                );
                $this->recordChange($organisationSource, $rawMaterial->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'RawMaterial', 'update');

                return null;
            }
        } else {
            try {
                $rawMaterial = StoreRawMaterial::make()->action(
                    production: $data['production'],
                    modelData: $modelData
                );
                $this->recordNew($organisationSource);

                $sourceData = explode(':', $rawMaterial->source_id);
                DB::connection('aurora')->table('Raw Material Dimension')
                    ->where('Raw Material Key', $sourceData[1])
                    ->update(['aiku_id' => $rawMaterial->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'RawMaterial', 'store');

                return null;
            }
        }

        return $rawMaterial;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Raw Material Dimension')
            ->select('Raw Material Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Raw Material Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Raw Material Dimension')->update(['aiku_id' => null]);
    }
}
