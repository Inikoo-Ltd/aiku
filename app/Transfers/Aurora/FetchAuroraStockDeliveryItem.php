<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:34:42 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Facades\DB;

class FetchAuroraStockDeliveryItem extends FetchAurora
{
    protected function parseStockDeliveryItem(StockDelivery $stockDelivery): void
    {

        $orgStock = null;
        if ($this->auroraModelData->{'Purchase Order Transaction Part SKU'}) {
            $orgStock = $this->parseOrgStock($this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Transaction Part SKU'});
        }
        if (!$orgStock) {
            print "SD  ".$this->auroraModelData->{'Supplier Delivery Key'}."  SKU not found (".$this->auroraModelData->{'Purchase Order Transaction Part SKU'}.")   ".$this->auroraModelData->{'Purchase Order Transaction Fact Key'}."  \n";
            return;
        }


        $historicSupplierProduct = null;
        if (!($stockDelivery->parent_type == 'OrgPartner' || $stockDelivery->parent_type == 'Production')) {
            $historicSupplierProduct = $this->parseHistoricSupplierProduct($this->organisation->id, $this->auroraModelData->{'Supplier Part Historic Key'});
        }


        if (!$historicSupplierProduct && !$orgStock) {
            print "SD  ".$this->auroraModelData->{'Supplier Delivery Key'}."  Transaction Item not found   ".$this->auroraModelData->{'Purchase Order Transaction Fact Key'}."  \n";

            return;
        }


        $this->parsedData['historic_supplier_product'] = $historicSupplierProduct;
        $this->parsedData['org_stock']                 = $orgStock;

        //enum('Cancelled','NoReceived','InProcess','Dispatched','Received','Checked','Placed','CostingDone')
        $state = match ($this->auroraModelData->{'Supplier Delivery Transaction State'}) {
            'Cancelled' => StockDeliveryItemStateEnum::CANCELLED,
            'NoReceived' => StockDeliveryItemStateEnum::NOT_RECEIVED,
            'InProcess' => StockDeliveryItemStateEnum::IN_PROCESS,
            'Dispatched' => StockDeliveryItemStateEnum::DISPATCHED,
            'Received' => StockDeliveryItemStateEnum::RECEIVED,
            'Checked' => StockDeliveryItemStateEnum::CHECKED,
            'Placed', 'CostingDone' => StockDeliveryItemStateEnum::PLACED,
        };


        $this->parsedData['stock_delivery_item'] = [
            'unit_quantity'         => !$this->auroraModelData->{'Supplier Delivery Units'} ? 0 : $this->auroraModelData->{'Supplier Delivery Units'},
            'unit_quantity_checked' => !$this->auroraModelData->{'Supplier Delivery Checked Units'} ? 0 : $this->auroraModelData->{'Supplier Delivery Checked Units'},
            'unit_quantity_placed'  => !$this->auroraModelData->{'Supplier Delivery Placed Units'} ? 0 : $this->auroraModelData->{'Supplier Delivery Placed Units'},
            'state'                 => $state,
            'source_id'             => $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Transaction Fact Key'},
            'created_at'            => $this->auroraModelData->{'Creation Date'},
            'fetched_at'            => now(),
            'last_fetched_at'       => now(),


        ];
    }

    public function fetchAuroraStockDeliveryItem(int $id, StockDelivery $stockDelivery): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseStockDeliveryItem($stockDelivery);
        }

        return $this->parsedData;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Purchase Order Transaction Fact')
            ->where('Purchase Order Transaction Fact Key', $id)->first();
    }


}
