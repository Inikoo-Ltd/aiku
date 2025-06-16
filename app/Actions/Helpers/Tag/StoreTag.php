<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreTag extends OrgAction
{
    public function handle(Group|TradeUnit $parent, array $modelData): Tag
    {

        if ($parent instanceof TradeUnit) {
            $group = $parent->group;
            data_set($modelData, 'scope', TagScopeEnum::PRODUCT_PROPERTY);
        } else {
            $group = $parent;
            data_set($modelData, 'scope', TagScopeEnum::OTHER);
        }

        data_set($modelData, 'group_id', $group->id);

        $tag = Tag::create($modelData);

        $image = Arr::pull($modelData, 'image', null);
        if ($image) {
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $tag     = SaveModelImage::run(
                model: $tag,
                imageData: $imageData,
                scope: 'image',
            );
        }

        AttachTagsToModel::make()->handle(
            $parent,
            [
                'tags_id' => [$tag->id]
            ],
        );

        return $tag;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'image'                    => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

}
