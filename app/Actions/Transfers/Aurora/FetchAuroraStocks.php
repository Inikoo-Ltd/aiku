<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 00:35:52 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Inventory\OrgStock\SyncOrgStockLocations;
use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreOrgStockHasOrgSupplierProduct;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraStocks extends FetchAuroraAction
{
    use WithAuroraAttachments;
    use HasStockLocationsFetch;
    use WithFetchStock;


    public string $commandSignature = 'fetch:stocks {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): array
    {
        $orgStock    = null;
        $isPrincipal = false;
        $stockData   = $organisationSource->fetchStock($organisationSourceId);

        if (!$stockData) {
            return [
                'stock'    => null,
                'orgStock' => null
            ];
        }


        if ($stockData['abnormal']) {
            $tradeUnit = $stockData['trade_unit'];

            $orgStock = $this->processAbnormalOrgStock($organisationSource, $stockData);

            if (!$orgStock) {
                return [
                    'stock'    => null,
                    'orgStock' => null
                ];
            }


            SyncOrgStockTradeUnits::run($orgStock, [
                $tradeUnit->id => [
                    'quantity' => $stockData['stock']['units_per_pack']
                ]
            ]);


            return [
                'stock'    => null,
                'orgStock' => $orgStock
            ];
        }


        if ($stock = Stock::withTrashed()->where('source_id', $stockData['stock']['source_id'])->first()) {
            $dataToUpdate = Arr::only($stockData['stock'], [
                'name',
                'code',
                'activated_at',
                'discontinued_at',
                'state',
                'source_id',
                'source_slug',
                'fetched_at',
                'last_fetched_at',
                'created_at'
            ]);
            try {


                $stock       = UpdateStock::make()->action(
                    stock: $stock,
                    modelData: $dataToUpdate,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $isPrincipal = true;
                $this->recordChange($organisationSource, $stock->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $dataToUpdate, 'Stock', 'update');

                return [
                    'stock'    => null,
                    'orgStock' => null
                ];
            }
        }


        if (!$stock) {
            $stock = Stock::withTrashed()->whereJsonContains('sources->parts', $stockData['stock']['source_id'])->first();
        }

        if (!$stock) {
            $stock = Stock::withTrashed()->where('source_slug', $stockData['stock']['source_slug'])->first();
        }

        if (!$stock) {
            if ($stockData['stock_family']) {
                $parent = $stockData['stock_family'];
            } else {
                $parent = $organisationSource->getOrganisation()->group;
            }
            try {
                $stock       = StoreStock::make()->action(
                    parent: $parent,
                    modelData: $stockData['stock'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $isPrincipal = true;
                Stock::enableAuditing();
                $this->saveMigrationHistory(
                    $stock,
                    Arr::except($stockData['stock'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->updateStockSources($stock, $stockData['stock']['source_id']);

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $stock->source_id);
                DB::connection('aurora')->table('Part Dimension')
                    ->where('Part SKU', $sourceData[1])
                    ->update(['aiku_id' => $stock->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $stockData['stock'], 'Stock', 'store');

                return [
                    'stock'    => null,
                    'orgStock' => null
                ];
            }
        }

        if ($stock) {
            if ($isPrincipal) {
                $tradeUnit = $stockData['trade_unit'];

                SyncStockTradeUnits::run($stock, [
                    $tradeUnit->id => [
                        'quantity' => $stockData['stock']['units_per_pack']
                    ]
                ]);
            }


            if ($stock->state == StockStateEnum::IN_PROCESS and $stockData['org_stock']['state'] != StockStateEnum::IN_PROCESS) {
                $stock = UpdateStock::make()->action(
                    stock: $stock,
                    modelData: [
                        'state' => StockStateEnum::ACTIVE
                    ],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            }


            if ($stock->state != StockStateEnum::IN_PROCESS) {

                $orgStock = $this->processOrgStock($organisationSource, $stock, $stockData);

                if ($orgStock) {
                    $locationsData = $this->getStockLocationData($organisationSource, $stockData['stock']['source_id']);
                    SyncOrgStockLocations::make()->action($orgStock, [
                        'locationsData' => $locationsData
                    ], 60, false);
                }
            }
            //$tradeUnit = $stock->tradeUnits()->first();
            //$this->processFetchAttachments($tradeUnit, 'Part', $stockData['stock']['source_id']);


            if ($isPrincipal) {
                $stock->supplierProducts()->syncWithoutDetaching($stockData['supplier_products']);
            } else {
                foreach ($stockData['supplier_products'] as $supplierProductId => $supplierProductData) {
                    if (!$stock->supplierProducts()->where('supplier_product_id', $supplierProductId)->exists()) {
                        $stock->supplierProducts()->attach(
                            $supplierProductId,
                            [
                                'available' => $supplierProductData['available']
                            ]
                        );
                    }
                }
            }

            if ($orgStock) {
                foreach ($stockData['org_supplier_products'] as $orgSupplierProductId => $orgSupplierProductData) {
                    $orgStockHasOrgSupplierProduct = $orgStock->orgSupplierProducts()->where('org_supplier_product_id', $orgSupplierProductId)->first();

                    if (!$orgStockHasOrgSupplierProduct) {
                        $stockHasSupplierProduct = StockHasSupplierProduct::where('stock_id', $stock->id)->where('supplier_product_id', $orgSupplierProductData['supplier_product_id'])->first();

                        /** @var OrgSupplierProduct $orgSupplierProduct */
                        $orgSupplierProduct = OrgSupplierProduct::find($orgSupplierProductId);

                        data_forget($orgSupplierProductData, 'supplier_product_id');

                        StoreOrgStockHasOrgSupplierProduct::make()->action(
                            stockHasSupplierProduct: $stockHasSupplierProduct,
                            orgStock: $orgStock,
                            orgSupplierProduct: $orgSupplierProduct,
                            modelData: $orgSupplierProductData
                        );
                    }
                }
            }


            $sourceData = explode(':', $stockData['stock']['source_id']);
            DB::connection('aurora')->table('Part Dimension')
                ->where('Part SKU', $sourceData[1])
                ->update(['aiku_id' => $stock->id]);

            return [
                'stock'    => $stock,
                'orgStock' => $orgStock
            ];
        }


        return [
            'stock'    => null,
            'orgStock' => null
        ];
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Part Dimension')
            ->select('Part SKU as source_id')
            ->when(app()->environment('testing'), function ($query) {
                return $query->limit(20);
            });

        if ($this->onlyNew) {
            $query->whereNull('aiku_id')->whereNull('aiku_unit_id');
        }
        $query->orderBy('Part Valid From');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Part Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id')->whereNull('aiku_unit_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Part Dimension')->update(['aiku_id' => null]);
    }
}
