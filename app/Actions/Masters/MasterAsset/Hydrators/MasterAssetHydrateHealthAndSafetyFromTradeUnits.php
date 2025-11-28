<?php

/*
 * author Louis Perez
 * created on 26-11-2025-14h-53m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Models\Masters\MasterAsset;
use App\Models\Goods\TradeUnit;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateHealthAndSafetyFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use HasDangerousGoodsFields;
    use HasProductInformation;

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $tradeUnits = $masterAsset->tradeUnits;
        if ($tradeUnits->count() == 1) {
            $this->updateFromASingleTradeUnit($tradeUnits->first(), $masterAsset);
        } else {
            $this->updateFromMultipleTradeUnits($tradeUnits, $masterAsset);
        }
    }

    public function updateFromASingleTradeUnit(TradeUnit $tradeUnit, MasterAsset $masterAsset): void
    {
        $dangerousGoodsFields     = $this->getDangerousGoodsFieldNames();
        $masterAssetInformationFields = $this->getProductInformationFieldNames();

        $dataToUpdate = [];

        foreach (array_merge($dangerousGoodsFields, $masterAssetInformationFields) as $field) {
            if ($tradeUnit->$field !== null) {
                $dataToUpdate[$field] = $tradeUnit->$field;
            }
        }

        if (!empty($dataToUpdate)) {
            $masterAsset->updateQuietly($dataToUpdate);
        }
    }

    public function updateFromMultipleTradeUnits($tradeUnits, MasterAsset $masterAsset): void
    {
        $dangerousGoodsFields     = $this->getDangerousGoodsFieldNames();
        $masterAssetInformationFields = $this->getProductInformationFieldNames();

        $dataToUpdate = [];

        foreach (array_merge($dangerousGoodsFields, $masterAssetInformationFields) as $field) {
            $values  = [];
            $hasTrue = false;

            // Collect all non-null values for this field from all trade units
            foreach ($tradeUnits as $tradeUnit) {
                if ($tradeUnit->$field !== null) {
                    // For boolean fields, check if any value is true
                    if (is_bool($tradeUnit->$field)) {
                        if ($tradeUnit->$field) {
                            $hasTrue = true;
                        }
                    } else {
                        $values[] = $tradeUnit->$field;
                    }
                }
            }

            // For boolean fields, if any value is true, set it as true for the product
            if ($hasTrue) {
                $dataToUpdate[$field] = true;
            } // For non-boolean fields, if we have values, concatenate them with comma separators
            elseif (!empty($values)) {
                $dataToUpdate[$field] = implode(', ', array_unique($values));
            }
        }

        if (!empty($dataToUpdate)) {
            $masterAsset->updateQuietly($dataToUpdate);
        }
    }

}
