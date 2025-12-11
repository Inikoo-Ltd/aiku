<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tag, $this->validatedData);
    }

    public function inCustomer(Customer $customer, Tag $tag, ActionRequest $request): void
    {
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($tag, $this->validatedData);
    }

    public function inSelfFilledTag(Organisation $organisation, Shop $shop, Tag $tag, ActionRequest $request): RedirectResponse
    {
        try {
            $this->initialisationFromShop($shop, $request);

            $this->handle($tag, $this->validatedData);

            return Redirect::route('grp.org.shops.show.crm.self_filled_tags.index', [
                $this->organisation->slug,
                $this->shop->slug
            ])->with('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tag updated.'),
            ]);
        } catch (Exception $e) {
            return Redirect::route('grp.org.shops.show.crm.self_filled_tags.index', [
                $this->organisation->slug,
                $this->shop->slug
            ])->with('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function handle(Tag $tag, array $modelData): Tag
    {
        $image = Arr::pull($modelData, 'image');

        if ($image) {
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];

            $tag = SaveModelImage::run(
                model: $tag,
                imageData: $imageData,
                scope: 'image',
            );

            if ($tag->image) {
                $tag->update(
                    [
                        'web_image' => $tag->imageSources(30, 30)
                    ]
                );
            }
        }

        $tag->update($modelData);

        return $tag;
    }

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255', 'unique:tags,name,' . request()->route('tag')->id],
            'scope' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', array_column(TagScopeEnum::cases(), 'value')),
                function ($attribute, $value, $fail) {
                    if ($value === TagScopeEnum::SYSTEM_CUSTOMER->value) {
                        $fail(__("You can't update tag with system scope."));
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
