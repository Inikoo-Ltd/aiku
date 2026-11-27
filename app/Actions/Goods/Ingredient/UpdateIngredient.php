<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-14h-16m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Goods\Ingredient;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\Ingredient;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateIngredient extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Ingredient $ingredient;


    public function handle(Ingredient $ingredient, array $modelData): Ingredient
    {
        $oldName    = $ingredient->name;
        $ingredient = $this->update($ingredient, $modelData, ['data']);

        if ($ingredient->wasChanged('name')) {
            RenameIngredientInTradeUnits::dispatch($ingredient, $oldName);
        }

        return $ingredient;
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
        $rules = [
            'name'               => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'ingredients',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->ingredient->group_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->ingredient->id
                        ]
                    ]
                ),
            ],
            ];

        if (!$this->strict) {
            $rules['source_data'] = ['sometimes','nullable', 'array'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Ingredient $ingredient, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Ingredient
    {
        $this->strict = $strict;
        if (!$audit) {
            Ingredient::disableAuditing();
        }
        $this->asAction       = true;
        $this->ingredient       = $ingredient;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($ingredient->group, $modelData);

        return $this->handle($ingredient, $this->validatedData);
    }

    public function asController(Ingredient $ingredient, ActionRequest $request): Ingredient
    {
        $this->ingredient = $ingredient;
        $this->initialisationFromGroup($ingredient->group, $request);
        return $this->handle($ingredient, $this->validatedData);
    }
}
