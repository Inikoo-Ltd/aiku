<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 20:10:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateHeathAndSafetyFromTradeUnits implements ShouldBeUnique
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

        if ($tradeUnits->isEmpty()) {
            return;
        }

        $dataToUpdate = $tradeUnits->count() == 1
            ? $this->dataFromASingleTradeUnit($tradeUnits->first())
            : $this->dataFromMultipleTradeUnits($tradeUnits);

        $product->update($dataToUpdate);

        if ($product->wasChanged() && $product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            BreakWebpageCache::dispatch($product->webpage)->delay(5);
        }
    }

    public function dataFromASingleTradeUnit(TradeUnit $tradeUnit): array
    {
        $dataToUpdate = [];

        foreach ($this->hydratedFieldNames() as $field) {
            if ($tradeUnit->$field !== null || $this->isOwnedByTradeUnits($field)) {
                $dataToUpdate[$field] = $tradeUnit->$field;
            }
        }

        return $dataToUpdate;
    }

    public function dataFromMultipleTradeUnits($tradeUnits): array
    {
        $dataToUpdate = [];

        foreach ($this->hydratedFieldNames() as $field) {
            $values  = [];
            $hasTrue = false;

            foreach ($tradeUnits as $tradeUnit) {
                if ($tradeUnit->$field !== null) {
                    if (is_bool($tradeUnit->$field)) {
                        $hasTrue = $hasTrue || $tradeUnit->$field;
                    } else {
                        $values[] = $tradeUnit->$field;
                    }
                }
            }

            if ($hasTrue) {
                $dataToUpdate[$field] = true;
            } elseif (empty($values)) {
                if ($this->isOwnedByTradeUnits($field)) {
                    $dataToUpdate[$field] = null;
                }
            } elseif ($field == 'origin_country_id') {
                $dataToUpdate[$field] = $values[0];
            } else {
                $dataToUpdate[$field] = implode(', ', array_unique($values));
            }
        }

        return $dataToUpdate;
    }

    /**
     * Fields the trade units are the sole source of truth for, so a null there must
     * blank the product. The rest (GPSR texts, dangerous goods, pictograms) are also
     * populated per shop and by hand, so a trade unit with nothing to say leaves them.
     */
    private function isOwnedByTradeUnits(string $field): bool
    {
        return in_array($field, ['country_of_origin', 'origin_country_id']);
    }

    private function hydratedFieldNames(): array
    {
        return array_merge($this->getDangerousGoodsFieldNames(), $this->getProductInformationFieldNames());
    }
}
