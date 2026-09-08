<?php

/*
 * Copyright 2026
*/

namespace App\Actions\Goods\Ingredient\Json;

use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\Ingredient;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ParseIngredientsList extends OrgAction
{
    use WithGoodsEditAuthorisation;

    /**
     * @return array<int, array{name: string, slug: string|null, is_new: bool}>
     */
    public function handle(Group $group, string $text, bool $commit): array
    {
        $names = $this->extractNames($text);

        $existing = Ingredient::where('group_id', $group->id)
            ->whereIn(DB::raw('lower(name)'), array_map('mb_strtolower', $names))
            ->get()
            ->keyBy(fn (Ingredient $ingredient) => mb_strtolower($ingredient->name));

        return collect($names)->map(function (string $name) use ($group, $existing, $commit) {
            $ingredient = $existing->get(mb_strtolower($name));

            if ($ingredient) {
                return ['name' => $ingredient->name, 'slug' => $ingredient->slug, 'is_new' => false];
            }

            if (!$commit) {
                return ['name' => $name, 'slug' => null, 'is_new' => true];
            }

            $ingredient = StoreIngredient::make()->action($group, ['name' => $name]);

            return ['name' => $ingredient->name, 'slug' => $ingredient->slug, 'is_new' => true];
        })->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractNames(string $text): array
    {
        return collect(preg_split('/[,;\r\n]+/', $text))
            ->map(fn ($name) => trim(preg_replace('/[*†\x{00B0}]+$/u', '', trim($name))))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'text'   => ['required', 'string', 'max:20000'],
            'commit' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $this->validatedData['text'], (bool)($this->validatedData['commit'] ?? false));
    }
}
