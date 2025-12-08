<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 11:16:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Models\Production\Artefact;
use App\Transfers\SourceOrganisationService;
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
                UpdateArtefact::make()->action(
                    artefact: $artefact,
                    modelData: $artefactData['artefact'],
                    hydratorsDelay: $this->hydratorsDelay
                );
            } else {
                $artefact = StoreArtefact::make()->action(
                    production: $artefactData['production'],
                    modelData: $artefactData['artefact'],
                    hydratorsDelay: $this->hydratorsDelay
                );

                $sourceData = explode(':', $artefact->source_id);
                DB::connection('aurora')->table('Supplier Part Dimension')
                    ->where('Supplier Part Key', $sourceData[1])
                    ->update(['aiku_id' => $artefact->id]);
            }

            return $artefact;
        }

        return null;
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
