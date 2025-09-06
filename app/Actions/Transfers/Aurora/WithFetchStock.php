<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Oct 2024 19:02:15 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\OrgStock\StoreAbnormalOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Support\Arr;
use Throwable;

trait WithFetchStock
{
    protected function processOrgStock(SourceOrganisationService $organisationSource, Stock $effectiveStock, array $stockData): OrgStock|null
    {
        $organisation = $organisationSource->getOrganisation();


        $orgStock = $organisation->orgStocks()->where('source_id', $stockData['stock']['source_id'])->first();

        if (!$orgStock) {
            $code = $stockData['stock']['code'];
            $orgStock = OrgStock::where('organisation_id', $organisation->id)
                ->whereRaw('LOWER(code) = LOWER(?)', [$code])
                ->first();
        }


        /** @var OrgStock $orgStock */
        if ($orgStock) {
            try {
                return UpdateOrgStock::make()->action(
                    orgStock: $orgStock,
                    modelData: $stockData['org_stock'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $stockData['org_stock'], 'OrgStock', 'update');

                return null;
            }
        } else {
            $orgParent = null;

            if ($effectiveStock->stockFamily) {
                $orgParent = $effectiveStock->stockFamily->orgStockFamilies()->where('organisation_id', $organisation->id)->first();
            }

            if (!$orgParent) {
                $orgParent = $organisationSource->getOrganisation();
            }
            try {
                $orgStock = StoreOrgStock::make()->action(
                    parent: $orgParent,
                    stock: $effectiveStock,
                    modelData: $stockData['org_stock'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                OrgStock::enableAuditing();
                $this->saveMigrationHistory(
                    $orgStock,
                    Arr::except($stockData['org_stock'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                return $orgStock;
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $stockData['org_stock'], 'OrgStock', 'store');

                return null;
            }
        }
    }


    protected function processAbnormalOrgStock(SourceOrganisationService $organisationSource, array $stockData): OrgStock|null
    {

        $orgStockData = $stockData['org_stock'];
        data_set($orgStockData, 'code', $stockData['stock']['code']);
        data_set($orgStockData, 'name', $stockData['stock']['name']);

        $organisation = $organisationSource->getOrganisation();
        /** @var OrgStock $orgStock */
        if ($orgStock = $organisation->orgStocks()->where('source_id', $stockData['stock']['source_id'])->first()) {
            try {
                return UpdateOrgStock::make()->action(
                    orgStock: $orgStock,
                    modelData: $orgStockData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $orgStockData, 'OrgStock', 'update');

                return null;
            }
        } else {


            try {
                $orgStock = StoreAbnormalOrgStock::make()->action(
                    parent: $organisation,
                    modelData: $orgStockData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                OrgStock::enableAuditing();
                $this->saveMigrationHistory(
                    $orgStock,
                    Arr::except($orgStockData, ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                return $orgStock;
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $orgStockData, 'OrgStock', 'store');

                return null;
            }
        }
    }


    public function updateStockSources(Stock $stock, string $source): void
    {
        $sources   = Arr::get($stock->sources, 'stocks', []);
        $sources[] = $source;
        $sources   = array_unique($sources);

        $stock->updateQuietly([
            'sources' => [
                'stocks' => $sources,
            ]
        ]);
    }

}
