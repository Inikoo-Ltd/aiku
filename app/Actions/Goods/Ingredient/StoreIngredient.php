<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-14h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Goods\Ingredient;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Goods\Ingredient;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class StoreIngredient extends OrgAction
{
    use WithNoStrictRules;

    public function handle(Group $group, array $modelData): Ingredient
    {
        return $group->ingredients()->create($modelData);
    }

    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'ingredients',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules['source_data'] = ['sometimes','nullable', 'array'];
            $rules                = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Ingredient
    {
        if (!$audit) {
            Ingredient::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    public function asController(Group $group, ActionRequest $request): Ingredient
    {
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $this->validatedData);
    }
}
