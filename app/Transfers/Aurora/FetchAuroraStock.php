<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraStockFamilies;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\StockFamily;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraStock extends FetchAurora
{
    use WithAuroraParsers;
    use WithAuroraImages;


    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Part Customer Key'}) {
            return;
        }

        $abnormal = false;
        if ($this->organisation->slug == 'aw' and $this->auroraModelData->{'Part Status'} == 'Not In Use') {
            $auroraSupplierProduct = DB::connection('aurora')
                ->table('Supplier Part Dimension')
                ->where('Supplier Part Part SKU', $this->auroraModelData->{'Part SKU'})->first();

            if (!$auroraSupplierProduct or $auroraSupplierProduct->{'Supplier Part Supplier Key'} == 1) {
                $abnormal = true;
            }
        }


        $tradeUnitReference = $this->cleanTradeUnitReference($this->auroraModelData->{'Part Reference'});
        $tradeUnitSlug      = Str::lower($tradeUnitReference);


        $this->parsedData['trade_unit'] = $this->parseTradeUnit(
            $tradeUnitSlug,
            $this->auroraModelData->{'Part SKU'}
        );

        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Part Reference'});
        $sourceSlug = Str::kebab(strtolower($code));

        $name = $this->auroraModelData->{'Part Recommended Product Unit Name'};
        if ($name == '') {
            $name = $this->auroraModelData->{'Part Package Description'};
        }
        if ($name == '') {
            $name = 'Not set';
        }

        $name = preg_replace('/\s+/', ' ', $name);

        $state = match ($this->auroraModelData->{'Part Status'}) {
            'In Use' => StockStateEnum::ACTIVE,
            'Discontinuing', 'Not In Use' => StockStateEnum::DISCONTINUED,
            'In Process' => StockStateEnum::IN_PROCESS,
        };

        if ($state == StockStateEnum::IN_PROCESS) {
            if (DB::connection('aurora')
                    ->table('Inventory Transaction Fact')
                    ->leftJoin('Part Dimension', 'Part Dimension.Part SKU', 'Inventory Transaction Fact.Part SKU')
                    ->whereIn('Inventory Transaction Section', ['In', 'Out'])
                    ->where('Inventory Transaction Fact.Part SKU', $this->auroraModelData->{'Part SKU'})->count() > 0) {
                $state = StockStateEnum::ACTIVE;
            }
        }

        $supplierProducts    = [];
        $orgSupplierProducts = [];

        foreach (
            DB::connection('aurora')
                ->table('Supplier Part Dimension')
                ->where('Supplier Part Part SKU', $this->auroraModelData->{'Part SKU'})->get() as $auroraSupplierProductData
        ) {
            $supplierProduct = $this->parseSupplierProduct($this->organisation->id.':'.$auroraSupplierProductData->{'Supplier Part Key'});

            if (!$supplierProduct) {
                continue;
            }

            $supplierProducts[$supplierProduct->id] = [
                'available' => $auroraSupplierProductData->{'Supplier Part Status'} == 'Available',
            ];

            $orgSupplierProduct = $this->parseOrgSupplierProduct($this->organisation->id.':'.$auroraSupplierProductData->{'Supplier Part Key'});
            if ($orgSupplierProduct) {
                $orgSupplierProducts[$orgSupplierProduct->id] = [
                    'supplier_product_id' => $supplierProduct->id,
                    'status'              => $auroraSupplierProductData->{'Supplier Part Status'} == 'Available',
                    'local_priority'      => $this->auroraModelData->{'Part Main Supplier Part Key'} == $auroraSupplierProductData->{'Supplier Part Key'} ? 10 : 0,
                ];
            }
        }

        $this->parsedData['supplier_products']     = $supplierProducts;
        $this->parsedData['org_supplier_products'] = $orgSupplierProducts;
        $this->parsedData['stock_family'] = $this->parseStockFamily($this->auroraModelData->{'Part SKU'});

        $createdAt = $this->parseDateTime($this->auroraModelData->{'Part Valid From'});

        $this->parsedData['abnormal'] = $abnormal;
        $this->parsedData['stock']    = [
            'name'            => $name,
            'code'            => $code,
            'activated_at'    => $this->parseDatetime($this->auroraModelData->{'Part Active From'}),
            'units_per_pack'  => $this->auroraModelData->{'Part Units Per Package'},
            'unit_value'      => $this->auroraModelData->{'Part Cost in Warehouse'},
            'discontinued_at' =>
                ($this->auroraModelData->{'Part Valid To'} && $this->auroraModelData->{'Part Status'} == 'Not In Use')
                    ? $this->parseDatetime($this->auroraModelData->{'Part Valid To'})
                    :
                    null,
            'state'           => $state,


            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Part SKU'},
            'source_slug'     => $sourceSlug,
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];

        if ($createdAt) {
            $this->parsedData['stock']['created_at'] = $createdAt;
        }

        $this->parsedData['org_stock'] = [

            'state'                           => match ($this->auroraModelData->{'Part Status'}) {
                'Discontinuing' => OrgStockStateEnum::DISCONTINUING,
                'Not In Use' => OrgStockStateEnum::DISCONTINUED,
                default => OrgStockStateEnum::ACTIVE,
            },
            'discontinued_in_organisation_at' =>
                ($this->auroraModelData->{'Part Valid To'} && $this->auroraModelData->{'Part Status'} == 'Not In Use')
                    ? $this->parseDatetime($this->auroraModelData->{'Part Valid To'})
                    :
                    null,

            'quantity_status' => match ($this->auroraModelData->{'Part Stock Status'}) {
                'Surplus' => 'excess',
                'Optimal' => 'ideal',
                'Low' => 'low',
                'Critical' => 'critical',
                'Out_Of_Stock' => 'out-of-stock',
                'Error' => 'error',
            },
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Part SKU'},
            'source_slug'     => $sourceSlug,
            'images'          => $this->parseImages(),
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];
    }

    private function parseImages(): array
    {
        $images = $this->getModelImagesCollection(
            'Part',
            $this->auroraModelData->{'Part SKU'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }

    private function parseStockFamily($sourceID): StockFamily|null
    {
        $stockFamily = null;

        if ($row = DB::connection('aurora')
            ->table('Category Bridge as B')
            ->leftJoin('Category Dimension as C', 'C.Category Key', 'B.Category Key')
            ->select('B.Category Key')
            ->where('Category Branch Type', 'Head')
            ->where('Subject Key', $sourceID)
            ->where('Subject', 'Part')->first()) {
            $stockFamily = FetchAuroraStockFamilies::run($this->organisationSource, $row->{'Category Key'});
        }

        return $stockFamily;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Part Dimension')
            ->where('Part SKU', $id)->first();
    }
}
