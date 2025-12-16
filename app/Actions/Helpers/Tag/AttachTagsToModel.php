<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTagsFromTradeUnits;
use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateTagsFromTradeUnits;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use Exception;
use Lorisleiva\Actions\ActionRequest;

class AttachTagsToModel extends OrgAction
{
    private ?TagScopeEnum $forcedScope = null;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        try {
            $this->forcedScope = TagScopeEnum::PRODUCT_PROPERTY;
            $this->initialisationFromGroup($tradeUnit->group, $request);
            $this->handle($tradeUnit, $this->validatedData);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (Exception $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function inCustomer(Customer $customer, ActionRequest $request): void
    {
        try {
            $this->forcedScope = TagScopeEnum::ADMIN_CUSTOMER;
            $this->initialisationFromShop($customer->shop, $request);
            $this->handle($customer, $this->validatedData);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (Exception $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function inRetina(Customer $customer, ActionRequest $request): void
    {
        try {
            $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
            $this->initialisationFromShop($customer->shop, $request);
            $this->handle($customer, $this->validatedData);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (Exception $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function action(TradeUnit|Customer $parent, array $modelData, $replace = false): void
    {
        try {
            if ($parent instanceof TradeUnit) {
                $this->forcedScope = TagScopeEnum::PRODUCT_PROPERTY;
                $this->initialisationFromGroup($parent->group, $modelData);
            } else {
                $this->initialisationFromShop($parent->shop, $modelData);
            }

            $this->handle($parent, $this->validatedData, $replace);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (Exception $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function handle(TradeUnit|Customer $model, array $modelData, $replace = false): void
    {
        if ($replace) {
            $model->tags()->sync($modelData['tags_id']);

            if ($model instanceof TradeUnit) {
                foreach ($model->products as $product) {
                    ProductHydrateTagsFromTradeUnits::run($product);
                }

                foreach ($model->masterAssets as $masterAsset) {
                    MasterAssetHydrateTagsFromTradeUnits::run($masterAsset);
                }
            }
        } else {
            $model->tags()->syncWithoutDetaching($modelData['tags_id']);
        }

        $model->refresh();

        foreach ($modelData['tags_id'] as $tagId) {
            TagHydrateModels::dispatch($tagId)->delay(300);
        }
    }

    public function rules(): array
    {
        return [
            'tags_id'   => ['sometimes', 'nullable', 'array'],
            'tags_id.*' => [
                'sometimes',
                'nullable',
                'exists:tags,id',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $exist = Tag::query();

                        if (isset($this->group)) {
                            $exist->where('group_id', $this->group->id);
                        }

                        if (isset($this->shop)) {
                            $exist->where('shop_id', $this->shop->id);
                        }

                        if ($this->forcedScope) {
                            $exist->where('scope', $this->forcedScope);
                        }

                        $exist = $exist->where('id', $value)->pluck('id')->toArray();

                        if (empty($exist)) {
                            $fail('Tag with ID ' . $value . ' is not applicable for this model.');
                        }
                    }
                }
            ],
        ];
    }
}
