<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Enums\Goods\Packaging\PackagingFamilySourceEnum;
use App\Enums\Goods\Packaging\PackagingLevelEnum;
use App\Models\Goods\PackagingFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Packaging weighed on one SKO at our warehouse becomes the packaging family of the SKO's trade unit, the record the
 * EPR returns read for every SKO, whoever supplied it. Weights are per piece, counts per SKO; an SKO of several units
 * shares its pieces between them. The family is measured and never one a supplier declared; the same pieces again
 * leave the trade unit as it is.
 */
class RecordMeasuredTradeUnitPackaging
{
    use AsAction;

    /**
     * @param list<array{material: string, weight_g: float|string, quantity?: float|string|null, description?: string|null}> $lines
     *
     * @return array{result: string, trade_unit: TradeUnit, packaging_family: PackagingFamily}
     */
    public function handle(OrgStock $orgStock, array $lines): array
    {
        $tradeUnits = $orgStock->tradeUnits()->get();
        if ($tradeUnits->count() !== 1) {
            throw ValidationException::withMessages(['lines' => __('Packaging can only be entered for an SKO made of one trade unit')]);
        }

        /** @var TradeUnit $tradeUnit */
        $tradeUnit   = $tradeUnits->first();
        $unitsPerSko = max(1.0, (float)$tradeUnit->pivot->quantity);
        $level       = $unitsPerSko > 1 ? PackagingLevelEnum::GROUPED : PackagingLevelEnum::PRIMARY;

        $components = array_map(function (array $line) use ($level, $unitsPerSko) {
            [$label, , , , $category, $polymer] = SyncPackingDropdownComponents::ITEMS[$line['material']];
            $quantity                           = (float)(Arr::get($line, 'quantity') ?: 1);

            return [
                'packaging_level'    => $level->value,
                'name'               => trim((string)Arr::get($line, 'description')) ?: $label,
                'material'           => $label,
                'material_category'  => $category,
                'polymer'            => $polymer,
                'weight_g'           => round((float)$line['weight_g'], 3),
                'weight_is_measured' => true,
                'quantity'           => $quantity,
                'quantity_per_unit'  => $quantity / $unitsPerSko,
            ];
        }, $lines);

        $family = StorePackagingFamilyFromComponents::run($tradeUnit->group, 'M-'.$tradeUnit->code, $tradeUnit->name, $components, PackagingFamilySourceEnum::MEASURED);

        if ($tradeUnit->packaging_family_id === $family->id) {
            return ['result' => 'unchanged', 'trade_unit' => $tradeUnit, 'packaging_family' => $family];
        }

        UpdateTradeUnit::make()->action($tradeUnit, ['packaging_family_id' => $family->id]);

        return ['result' => 'recorded', 'trade_unit' => $tradeUnit->refresh(), 'packaging_family' => $family];
    }
}
