<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 20:10:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Catalogue\Product\TranslateProductGpsrText;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateHeathAndSafetyFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use HasDangerousGoodsFields;
    use HasProductInformation;

    public const array CUSTOMS_FIELDS = ['tariff_code', 'country_of_origin', 'origin_country_id'];

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    /**
     * @param array<string>|null $onlyFields Restrict the write to these fields, used by the
     *                                       repair command so a bulk run cannot overwrite the
     *                                       shop's translated GPSR text with the trade unit's.
     */
    public function handle(Product $product, ?array $onlyFields = null): void
    {
        $tradeUnits = $product->tradeUnits;

        if ($tradeUnits->isEmpty()) {
            return;
        }

        $dataToUpdate = $this->expectedData($product);

        if ($onlyFields !== null) {
            $dataToUpdate = array_intersect_key($dataToUpdate, array_flip($onlyFields));
        }

        $gpsrFields               = array_keys(TranslateProductGpsrText::REVIEW_FLAGS);
        $translateProductGpsrText = TranslateProductGpsrText::make();

        [$gpsrAttributes, $gpsrFieldsToTranslate] = $translateProductGpsrText->fromSource($product, Arr::only($dataToUpdate, $gpsrFields));

        $product->update(array_merge(Arr::except($dataToUpdate, $gpsrFields), $gpsrAttributes));

        $translateProductGpsrText->recordShopTranslation($product, array_keys($product->getChanges()));
        $translateProductGpsrText->dispatchTranslations($product, $gpsrFieldsToTranslate);

        if ($product->wasChanged() && $product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            BreakWebpageCache::dispatch($product->webpage)->delay(5);
        }
    }

    public function expectedData(Product $product): array
    {
        $tradeUnits = $product->tradeUnits;

        if ($tradeUnits->count() == 1) {
            return $this->dataFromASingleTradeUnit($tradeUnits->first(), $product->organisation_id);
        }

        $data = $this->dataFromMultipleTradeUnits($tradeUnits, $product->organisation_id);

        if ($customsTradeUnit = $this->customsTradeUnit($product, $tradeUnits)) {
            foreach (self::CUSTOMS_FIELDS as $field) {
                $data[$field] = $this->fieldValue($customsTradeUnit, $field, $product->organisation_id);
            }
        }

        return $data;
    }

    /**
     * A product made of several trade units declares one of them to customs, so the invoice
     * carries a single tariff code and origin. Set on the product, else on the master it follows.
     */
    private function customsTradeUnit(Product $product, $tradeUnits): ?TradeUnit
    {
        $customsTradeUnitId = $product->customs_trade_unit_id
            ?? ($product->not_follow_master_trade_units ? null : $product->masterProduct?->customs_trade_unit_id);

        return $customsTradeUnitId ? $tradeUnits->firstWhere('id', $customsTradeUnitId) : null;
    }

    public function dataFromASingleTradeUnit(TradeUnit $tradeUnit, ?int $organisationId = null): array
    {
        $dataToUpdate = [];

        foreach ($this->hydratedFieldNames() as $field) {
            if ($tradeUnit->$field !== null || $this->isOwnedByTradeUnits($field)) {
                $dataToUpdate[$field] = $this->fieldValue($tradeUnit, $field, $organisationId);
            }
        }

        return $dataToUpdate;
    }

    public function dataFromMultipleTradeUnits($tradeUnits, ?int $organisationId = null): array
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
                        $values[] = $this->fieldValue($tradeUnit, $field, $organisationId);
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

    private function fieldValue(TradeUnit $tradeUnit, string $field, ?int $organisationId): mixed
    {
        return $field == 'tariff_code' ? $tradeUnit->getTariffCodeForOrganisation($organisationId) : $tradeUnit->$field;
    }

    private function hydratedFieldNames(): array
    {
        return array_merge($this->getDangerousGoodsFieldNames(), $this->getProductInformationFieldNames());
    }
}
