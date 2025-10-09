<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:24:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Traits\WithEnumStats;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitsHydrateMarketingIngredients implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {
        $materials = '';


        foreach ($tradeUnit->ingredients as $ingredient) {
            if ($ingredient->may_contain) {
                $may_contain_tag = '±';
            } else {
                $may_contain_tag = '';
            }

            $materials .= sprintf(
                ', %s%s',
                $may_contain_tag,
                $ingredient->name
            );

            if ($ingredient->ratio && $ingredient->ratio > 0) {
                $materials .= sprintf(
                    ' (%s)',
                    percentage($ingredient->ratio, 1)
                );
            }
        }

        $materials = preg_replace('/^, /', '', $materials);


        UpdateTradeUnit::run($tradeUnit, [
            'marketing_ingredients' => $materials,
        ]);
    }


}
