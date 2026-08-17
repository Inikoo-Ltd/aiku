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
            $dataToUpdate[$field] = $this->isPictogram($field) ? (bool)$tradeUnit->$field : $tradeUnit->$field;
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

            if ($this->isPictogram($field)) {
                $dataToUpdate[$field] = $hasTrue;
            } elseif (empty($values)) {
                $dataToUpdate[$field] = null;
            } elseif ($field == 'origin_country_id') {
                $dataToUpdate[$field] = $values[0];
            } else {
                $dataToUpdate[$field] = implode(', ', array_unique($values));
            }
        }

        return $dataToUpdate;
    }

    private function isPictogram(string $field): bool
    {
        return str_starts_with($field, 'pictogram_');
    }

    private function hydratedFieldNames(): array
    {
        return array_merge($this->getDangerousGoodsFieldNames(), $this->getProductInformationFieldNames());
    }
}
