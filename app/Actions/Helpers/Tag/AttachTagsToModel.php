<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\ActionRequest;

class AttachTagsToModel extends OrgAction
{
    protected TradeUnit $parent;
    public function handle(TradeUnit $model, array $modelData, $replace = false): void
    {
        if ($replace) {
            $model->tags()->sync($modelData['tags_id']);
        } else {
            $model->tags()->syncWithoutDetaching($modelData['tags_id']);
        }
        $model->refresh();

        foreach ($modelData['tags_id'] as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag) {
                TagHydrateModels::dispatch($tag);
            }
        }
    }

    public function rules(): array
    {
        return [
            'tags_id'   => ['required', 'array'],
            'tags_id.*' => [
                'required',
                'exists:tags,id',
                function ($attribute, $value, $fail) {
                    $exist = \DB::table('tags')
                        ->where('group_id', $this->group->id);
                    if ($this->parent instanceof TradeUnit) {
                        $exist->where('scope', TagScopeEnum::PRODUCT_PROPERTY->value);
                    } else {
                        $exist->where('scope', TagScopeEnum::OTHER->value);
                    }
                    $exist = $exist->where('id', $value)
                    ->pluck('user_id')
                    ->toArray();

                    if (empty($exist)) {
                        $fail('Tag with ID ' . $value . ' is not applicable for this model.');
                    }
                }
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->parent = $tradeUnit;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData, true);
    }


}
