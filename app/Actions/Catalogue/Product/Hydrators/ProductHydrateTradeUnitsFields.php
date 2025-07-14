<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 20:10:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateTradeUnitsFields implements ShouldBeUnique
{
    use AsAction;
    use HasDangerousGoodsFields;
    use HasProductInformation;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $tradeUnits = $product->tradeUnits;
        if ($tradeUnits->count() == 1) {
            $this->updateFromASingleTradeUnit($tradeUnits->first(), $product);
        } else {
            $this->updateFromMultipleTradeUnits($tradeUnits, $product);
        }
    }

    public function updateFromASingleTradeUnit(TradeUnit $tradeUnit, Product $product): void
    {
        $dangerousGoodsFields     = $this->getDangerousGoodsFieldNames();
        $productInformationFields = $this->getProductInformationFieldNames();

        $dataToUpdate = [];

        foreach (array_merge($dangerousGoodsFields, $productInformationFields) as $field) {
            if ($tradeUnit->$field !== null) {
                $dataToUpdate[$field] = $tradeUnit->$field;
            }
        }

        if (!empty($dataToUpdate)) {
            $product->updateQuietly($dataToUpdate);
        }
    }

    public function updateFromMultipleTradeUnits($tradeUnits, Product $product): void
    {
        $dangerousGoodsFields     = $this->getDangerousGoodsFieldNames();
        $productInformationFields = $this->getProductInformationFieldNames();

        $dataToUpdate = [];

        foreach (array_merge($dangerousGoodsFields, $productInformationFields) as $field) {
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
            $product->updateQuietly($dataToUpdate);
        }
    }

}
