<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 24 Oct 2022 11:01:21 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Models\Goods\StockFamily;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraStockFamilies extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:stock_families {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?StockFamily
    {
        $stockFamilyData = $organisationSource->fetchStockFamily($organisationSourceId);

        if (! $stockFamilyData) {
            return null;
        }

        if ($stockFamily = StockFamily::where('source_id', $stockFamilyData['stock_family']['source_id'])->first()) {
            return UpdateStockFamily::make()->action(
                stockFamily: $stockFamily,
                modelData: $stockFamilyData['stock_family'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );
        }

        $baseStockFamily = StockFamily::withTrashed()->where('source_slug', $stockFamilyData['stock_family']['source_slug'])->first();

        if (! $baseStockFamily) {

            $stockFamily = StoreStockFamily::make()->action(
                group: $organisationSource->getOrganisation()->group,
                modelData: $stockFamilyData['stock_family'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );
        }

        $organisation = $organisationSource->getOrganisation();

        $effectiveStockFamily = $stockFamily ?? $baseStockFamily;

        if (! $effectiveStockFamily->orgStockFamilies()->where('organisation_id', $organisation->id)->first()) {
            StoreOrgStockFamily::run($organisation, $effectiveStockFamily, [
                'source_id' => $stockFamilyData['stock_family']['source_id'],
            ]);
        }

        return $effectiveStockFamily;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Part Category Dimension', 'Category Key', 'Part Category Key')
            ->select('Category Key as source_id')
            ->where('Category Branch Type', 'Head')
            ->where('Category Scope', 'Part')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Category Dimension')
            ->where('Category Branch Type', 'Head')
            ->where('Category Scope', 'Part')
            ->count();
    }
}
