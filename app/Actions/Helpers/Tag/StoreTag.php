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

class StoreTag extends OrgAction
{
    private ?TagScopeEnum $forcedScope = null;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->forcedScope = TagScopeEnum::PRODUCT_PROPERTY;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): void
    {
        $this->forcedScope = TagScopeEnum::ADMIN_CUSTOMER;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);
    }

    public function inSelfFilledTag(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        try {
            $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
            $this->initialisationFromShop($shop, $request);

            $this->handle($shop, $this->validatedData);

            return Redirect::route('grp.org.shops.show.crm.self_filled_tags.index', [
                $this->organisation->slug,
                $this->shop->slug
            ])->with('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tag created.'),
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

    public function handle(Shop|Customer|TradeUnit $parent, array $modelData): Tag
    {
        if (isset($this->group)) {
            data_set($modelData, 'group_id', $this->group->id);
        }

        if (isset($this->organisation)) {
            data_set($modelData, 'organisation_id', $this->organisation->id);
        }

        if (isset($this->shop)) {
            data_set($modelData, 'shop_id', $this->shop->id);
        }

        if ($this->forcedScope) {
            data_set($modelData, 'scope', $this->forcedScope);
        }

        $image = Arr::pull($modelData, 'image');

        $tag = Tag::create($modelData);

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
