<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Aug 2024 11:20:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Inventory\Location\StoreLocation;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrgStockMovement extends FetchAurora
{
    protected function parseModel(): void
    {
        if (!in_array(
            $this->auroraModelData->{'Inventory Transaction Record Type'},
            ['Movement', 'Helper', 'Info']
        )) {
            return;
        }

        if ($this->auroraModelData->{'Inventory Transaction Record Type'}=='Info' && $this->auroraModelData->{'Inventory Transaction Type'} != 'Audit') {
            return;
        }


        if ($this->auroraModelData->aiku_picking_id) {
            return;
        }

        if (in_array($this->auroraModelData->{'Inventory Transaction Type'}, ['Move Out', 'Move In']) && $this->auroraModelData->{'Inventory Transaction Quantity'} == 0) {
            return;
        }


        $type        = null;
        $isDelivered = false;

        $quantity = $this->auroraModelData->{'Inventory Transaction Quantity'};


        if ($this->auroraModelData->{'Inventory Transaction Type'} == 'Sale') {
            $type        = OrgStockMovementTypeEnum::PICKED;
            $isDelivered = true;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Adjust') {
            $type = OrgStockMovementTypeEnum::ADJUSTMENT;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'In') {
            $type = OrgStockMovementTypeEnum::PURCHASE;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Found') {
            $type = OrgStockMovementTypeEnum::FOUND;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Restock') {
            $type = OrgStockMovementTypeEnum::RETURN_PICKED;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Other Out') {
            if ($this->auroraModelData->{'Inventory Transaction Section'} == 'Lost') {
                $type = OrgStockMovementTypeEnum::WRITE_OFF;
            }
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'FailSale') {
            if ($this->auroraModelData->{'Inventory Transaction Record Type'} == 'Movement') {
                $type = OrgStockMovementTypeEnum::PICKED;
            } else {
                return;
            }
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Production') {
            if ($this->auroraModelData->{'Inventory Transaction Section'} == 'In') {
                $type = OrgStockMovementTypeEnum::RETURN_CONSUMPTION;
            } else {
                $type = OrgStockMovementTypeEnum::CONSUMPTION;
            }
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Move In') {
            $type = OrgStockMovementTypeEnum::LOCATION_TRANSFER;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Move Out') {
            $type = OrgStockMovementTypeEnum::LOCATION_TRANSFER;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Lost') {
            $type = OrgStockMovementTypeEnum::WRITE_OFF;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Broken') {
            $type = OrgStockMovementTypeEnum::WRITE_OFF;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Associate') {
            $quantity = 0;
            $type     = OrgStockMovementTypeEnum::ASSOCIATE;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Disassociate') {
            $quantity = 0;
            $type     = OrgStockMovementTypeEnum::DISASSOCIATE;
        } elseif ($this->auroraModelData->{'Inventory Transaction Type'} == 'Audit') {
            $quantity = $this->auroraModelData->{'Part Location Stock'};
            $type     = OrgStockMovementTypeEnum::AUDIT;
        }
        if (!$type) {
            dd($this->auroraModelData);
        }

        if (abs($quantity) < 0.001) {
            $quantity = 0;
        }

        if ($quantity == 0 && !in_array($type, [
                OrgStockMovementTypeEnum::ASSOCIATE,
                OrgStockMovementTypeEnum::DISASSOCIATE,
                OrgStockMovementTypeEnum::AUDIT,
            ])) {
            return;
        }

        if ($quantity > 0 && $type == OrgStockMovementTypeEnum::WRITE_OFF) {
            $type = OrgStockMovementTypeEnum::FOUND;
        }

        if ($quantity < 0 && $type == OrgStockMovementTypeEnum::PURCHASE) {
            $type = OrgStockMovementTypeEnum::FOUND;
        }


        $orgStock = $this->parseOrgStock($this->organisation->id.':'.$this->auroraModelData->{'Part SKU'});
        if (!$orgStock) {
            return;
        }


        $location = $this->parseLocation($this->organisation->id.':'.$this->auroraModelData->{'Location Key'}, $this->organisationSource);

        if (!$location) {
            $locationCode = 'not_found_aiku_'.$this->organisation->id.'_'.$this->auroraModelData->{'Location Key'};

            $location = Location::withTrashed()->where('code', $locationCode)->first();

            if (!$location) {
                $deletedAtAuroraData = DB::connection('aurora')->table('Inventory Transaction Fact')
                    ->where('Location Key', $this->auroraModelData->{'Location Key'})
                    ->select('Date')
                    ->orderBy('Date', 'desc')->first();


                $deletedAt = $this->parseDatetime($deletedAtAuroraData->Date)->addHours(2);


                $warehouse = $this->parseWarehouse($this->organisation->id.':'.$this->auroraModelData->{'Warehouse Key'});


                $location = StoreLocation::make()->action(
                    parent: $warehouse,
                    modelData: [
                        'code'       => $locationCode,
                        'deleted_at' => $deletedAt,
                        'data'       => [
                            'not_found_while_fetching'          => true,
                            'not_found_while_fetching_metadata' => [
                                'command'   => 'fetch:stock_movements',
                                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Inventory Transaction Key'},
                            ]

                        ]
                    ],
                    hydratorsDelay: 60,
                    strict: false
                );
            }
        }

        $date = $this->parseDatetime($this->auroraModelData->{'Date'});


        $this->parsedData['orgStock'] = $orgStock;
        $this->parsedData['location'] = $location;


        $this->parsedData['orgStockMovement'] = [
            'is_delivered'    => $isDelivered,
            'type'            => $type,
            'quantity'        => $quantity,
            'org_amount'      => $this->auroraModelData->{'Inventory Transaction Amount'},
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Inventory Transaction Key'},
            'date'            => $date,
            'fetched_at'      => now(),
            'last_fetched_at' => now()
        ];
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Inventory Transaction Fact')
            ->where('Inventory Transaction Key', $id)->first();
    }
}
