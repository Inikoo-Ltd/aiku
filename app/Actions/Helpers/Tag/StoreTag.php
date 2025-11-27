<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit, $this->validatedData);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): Tag
    {
        $this->initialisation($customer->organisation, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Tag successfully created.'),
        ]);
    }

    public function handle(Organisation|Customer|TradeUnit $parent, array $modelData): Tag
    {
        if ($parent instanceof Customer) {
            data_set($modelData, 'scope', TagScopeEnum::ADMIN_CUSTOMER);
        }

        if ($parent instanceof TradeUnit) {
            data_set($modelData, 'scope', TagScopeEnum::PRODUCT_PROPERTY);
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
            $tag       = SaveModelImage::run(
                model: $tag,
                imageData: $imageData,
                scope: 'image',
            );
        }

        if ($parent instanceof TradeUnit || $parent instanceof Customer) {
            AttachTagsToModel::make()->handle($parent, ['tags_id' => [$tag->id]]);
        }

        if ($tag->image) {
            $tag->update(
                [
                    'web_image' => $tag->imageSources(30, 30)
                ]
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
                'in:'.implode(',', array_column(TagScopeEnum::cases(), 'value')),
                function ($attribute, $value, $fail) {
                    if ($value === TagScopeEnum::SYSTEM_CUSTOMER->value) {
                        $fail(__("You can't create tag with system scope."));
                    }
                },
            ],
            'image' => [
                'sometimes',
                'nullable',
                File::image()->max(12 * 1024),
            ],
        ];
    }
}
