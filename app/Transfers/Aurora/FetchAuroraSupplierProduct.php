<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraSupplierProduct extends FetchAurora
{
    use WithAuroraParsers;

    protected function parseModel(): void
    {



        if ($this->auroraModelData->aiku_ignore == 'Yes') {
            return;
        }


        $auroraSupplierData = DB::connection('aurora')
            ->table('Supplier Dimension')
            ->where('Supplier Key', $this->auroraModelData->{'Supplier Part Supplier Key'})
            ->first();


        if ($auroraSupplierData->aiku_ignore == 'Yes') {
            return;
        }

        $stock = $this->parseStock($this->organisation->id.':'.$this->auroraModelData->{'Supplier Part Part SKU'});
        if (!$stock) {
            return;
        }

        $supplierDeletedAt = $this->parseDate($auroraSupplierData->{'Supplier Valid To'});
        if ($auroraSupplierData->{'Supplier Type'} != 'Archived') {
            $supplierDeletedAt = null;
        }

        $supplierSourceSlug = Str::kebab(strtolower($auroraSupplierData->{'Supplier Code'}));
        if ($supplierDeletedAt) {
            $supplierSourceSlug .= '-deleted';
        }


        $supplier = $this->parseSupplier($supplierSourceSlug, $this->organisation->id.":".$this->auroraModelData->{'Supplier Part Supplier Key'});


        if (!$supplier) {
            return;
        }

        //print_r($this->auroraModelData);
        //print_r($supplier);

        $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();

        $this->parsedData['orgSupplier'] = $orgSupplier;


        $auroraPartData = DB::connection('aurora')
            ->table('Part Dimension')
            ->where('Part SKU', $this->auroraModelData->{'Supplier Part Part SKU'})
            ->first();

        $tradeUnitReference = $this->cleanTradeUnitReference($auroraPartData->{'Part Reference'});
        $tradeUnitSlug      = Str::lower($tradeUnitReference);

        $this->parsedData['trade_unit'] = $this->parseTradeUnit(
            $tradeUnitSlug,
            $auroraPartData->{'Part SKU'}
        );


        $this->parsedData['supplier'] = $supplier;

        $data     = [];
        $settings = [];

        $isAvailable = true;
        if ($this->auroraModelData->{'Supplier Part Status'} == 'NoAvailable') {
            $isAvailable = false;
        }
        $state = match ($this->auroraModelData->{'Supplier Part Status'}) {
            'Discontinued', 'NoAvailable' => SupplierProductStateEnum::DISCONTINUED,
            default => SupplierProductStateEnum::ACTIVE,
        };


        if ($this->auroraModelData->{'Supplier Part From'} == '0000-00-00 00:00:00') {
            $created_at = null;
        } else {
            $created_at = $this->auroraModelData->{'Supplier Part From'};
        }

        $data['original_unit_cost'] = $this->auroraModelData->{'Supplier Part Unit Cost'} ?? 0;
        $data['original_code']      = $this->auroraModelData->{'Supplier Part Reference'} ?? '';


        $stock_quantity_status = match ($auroraPartData->{'Part Stock Status'}) {
            'Out_Of_Stock', 'Error' => 'out-of-stock',
            default => strtolower($auroraPartData->{'Part Stock Status'})
        };

        $partReference      = $this->cleanTradeUnitReference($auroraPartData->{'Part Reference'});
        $sourceSlugInterOrg = $supplier->source_slug.':'.$this->auroraModelData->{'Supplier Part Packages Per Carton'}.':'.$auroraPartData->{'Part Units Per Package'}.':'.Str::kebab(strtolower($partReference));


        $sourceSlug = $supplier->source_slug.':'.Str::kebab(strtolower($partReference));


        $name = $this->auroraModelData->{'Supplier Part Description'};
        if ($name == '') {
            $name = $this->auroraModelData->{'Supplier Part Reference'};
        }


        $code = $this->auroraModelData->{'Supplier Part Reference'};
        // $code = str_replace('&', 'and', $code);
        // $code =$this->cleanTradeUnitReference($code);



        $this->parsedData['supplierProduct'] =
            [
                'code' => $code,
                'name' => $name,

                'cost'             => round($this->auroraModelData->{'Supplier Part Unit Cost'} ?? 0, 2),
                'units_per_pack'   => $auroraPartData->{'Part Units Per Package'},
                'units_per_carton' => $this->auroraModelData->{'Supplier Part Packages Per Carton'} * $auroraPartData->{'Part Units Per Package'},


                'is_available'          => $isAvailable,
                'state'                 => $state,
                'stock_quantity_status' => $stock_quantity_status,
                'stock_id'              => $stock->id,

                'data'                  => $data,
                'settings'              => $settings,
                'created_at'            => $created_at,
                'source_slug'           => $sourceSlug,
                'source_slug_inter_org' => $sourceSlugInterOrg,
                'source_id'             => $this->organisation->id.':'.$this->auroraModelData->{'Supplier Part Key'},
                'fetched_at'            => now(),
                'last_fetched_at'       => now()
            ];

        $this->parsedData['historicSupplierProductSourceID'] = $this->auroraModelData->{'Supplier Part Historic Key'};
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Supplier Part Dimension as ssp')
            ->where('ssp.aiku_ignore', 'No')
            ->where('Supplier Part Key', $id)->first();
    }
}
