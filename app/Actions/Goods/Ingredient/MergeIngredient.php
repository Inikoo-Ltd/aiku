<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Ingredient;

use App\Actions\OrgAction;
use App\Models\Goods\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MergeIngredient extends OrgAction
{
    private Ingredient $ingredient;

    /**
     * Moves every trade unit of a misspelt or duplicated ingredient onto the right one, keeping its
     * position, then deletes it. A trade unit that already lists both keeps one row.
     */
    public function handle(Ingredient $ingredient, Ingredient $into): Ingredient
    {
        $oldName = $ingredient->name;

        DB::transaction(function () use ($ingredient, $into) {
            $tradeUnitsWithBoth = DB::table('trade_unit_has_ingredients')
                ->where('ingredient_id', $into->id)
                ->pluck('trade_unit_id');

            DB::table('trade_unit_has_ingredients')
                ->where('ingredient_id', $ingredient->id)
                ->whereIn('trade_unit_id', $tradeUnitsWithBoth)
                ->delete();

            DB::table('trade_unit_has_ingredients')
                ->where('ingredient_id', $ingredient->id)
                ->update(['ingredient_id' => $into->id, 'updated_at' => now()]);

            $ingredient->delete();
        });

        RenameIngredientInTradeUnits::run($into, $oldName);

        return $into;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(['goods.edit', 'compliance.edit']);
    }

    public function rules(): array
    {
        return [
            'merge_into' => [
                'required',
                'string',
                Rule::exists('ingredients', 'slug')->where('group_id', $this->ingredient->group_id),
                Rule::notIn([$this->ingredient->slug]),
            ],
        ];
    }

    public function action(Ingredient $ingredient, Ingredient $into): Ingredient
    {
        $this->asAction   = true;
        $this->ingredient = $ingredient;
        $this->initialisationFromGroup($ingredient->group, ['merge_into' => $into->slug]);

        return $this->handle($ingredient, $into);
    }

    public function asController(Ingredient $ingredient, ActionRequest $request): Ingredient
    {
        $this->ingredient = $ingredient;
        $this->initialisationFromGroup($ingredient->group, $request);

        return $this->handle($ingredient, Ingredient::where('group_id', $ingredient->group_id)->where('slug', $this->validatedData['merge_into'])->firstOrFail());
    }

    public function htmlResponse(Ingredient $into): RedirectResponse
    {
        return redirect()->route('grp.goods.ingredients.show', $into->slug);
    }
}
