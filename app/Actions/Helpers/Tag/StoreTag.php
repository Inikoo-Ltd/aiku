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
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(Tag $tag): RedirectResponse
    {
        return Redirect::route('grp.org.tags.show', [$this->organisation->slug]);
    }

    public function handle(Organisation|TradeUnit $parent, array $modelData): Tag
    {
        if ($parent instanceof Organisation) {
            data_set($modelData, 'organisation_id', $parent->id);
        }

        if ($parent instanceof TradeUnit) {
            data_set($modelData, 'scope', TagScopeEnum::PRODUCT_PROPERTY);
        }

        if (!isset($modelData['scope'])) {
            data_set($modelData, 'scope', TagScopeEnum::OTHER);
        }

        data_set($modelData, 'group_id', $parent->group->id);

        $image = Arr::pull($modelData, 'image');

        $tag = Tag::create($modelData);

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


        if ($parent instanceof TradeUnit) {
            AttachTagsToModel::make()->handle(
                $parent,
                [
                    'tags_id' => [$tag->id]
                ],
            );
        }

        return $tag;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255', 'unique:tags,name'],
            'scope' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', array_column(TagScopeEnum::cases(), 'value')),
            ],
            'image' => [
                'sometimes',
                'nullable',
                File::image()->max(12 * 1024),
            ],
        ];
    }
}
