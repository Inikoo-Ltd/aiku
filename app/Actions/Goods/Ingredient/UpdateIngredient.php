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
        return $this->update($ingredient, $modelData, ['data']);
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
        $this->initialisationFromGroup($ingredient->group, $request);
        return $this->handle($ingredient, $this->validatedData);
    }
}
