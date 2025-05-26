<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag;

use App\Actions\Catalogue\Tag\Hydrators\TagHydrateModels;
use App\Actions\OrgAction;
use App\Models\Catalogue\Tag;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;

class AttachTagsToModel extends OrgAction
{
    public function handle(TradeUnit $model, array $modelData): void
    {
        $model->tags()->syncWithoutDetaching($modelData['tags_id']);

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
            'tags_id.*' => ['required', 'exists:tags,id'],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }


}
