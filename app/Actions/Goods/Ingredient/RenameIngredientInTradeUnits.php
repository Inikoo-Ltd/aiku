<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Ingredient;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Models\Goods\Ingredient;
use Lorisleiva\Actions\Concerns\AsAction;

class RenameIngredientInTradeUnits
{
    use AsAction;

    /**
     * Swaps the old name inside each trade unit's ingredients text instead of rebuilding
     * the text from trade_unit_has_ingredients: about 1,069 texts list ingredients or an
     * order the pivot rows do not have (HELP-4980), and a rebuild would drop them.
     * After a merge the new name may already be listed: the old one is then removed, not doubled.
     */
    public function handle(Ingredient $ingredient, string $oldName): void
    {
        $oldKey = mb_strtolower($oldName);
        $newKey = mb_strtolower($ingredient->name);

        foreach ($ingredient->tradeUnits()->whereNotNull('marketing_ingredients')->cursor() as $tradeUnit) {
            $parts   = SplitIngredientsText::run($tradeUnit->marketing_ingredients);
            $listed  = $oldKey !== $newKey && collect($parts)->contains(fn (string $part) => mb_strtolower(trim($part)) === $newKey);
            $renamed = false;

            foreach ($parts as $index => $part) {
                if (mb_strtolower(trim($part)) !== $oldKey) {
                    continue;
                }

                if ($listed) {
                    unset($parts[$index]);
                } else {
                    $parts[$index] = str_replace(trim($part), $ingredient->name, $part);
                    $listed        = $oldKey !== $newKey;
                }
                $renamed = true;
            }

            if ($renamed) {
                UpdateTradeUnit::run($tradeUnit, ['marketing_ingredients' => trim(implode(',', $parts))]);
            }
        }
    }
}
