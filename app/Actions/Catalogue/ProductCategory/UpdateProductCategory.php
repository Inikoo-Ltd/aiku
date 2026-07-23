<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:31:09 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\Product\Hydrators\ProductHydratePricesFromMaster;
use App\Actions\Discounts\Offer\FinishOffer;
use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Actions\Discounts\Offer\VolGr\StoreVolumeGRDiscount;
use App\Actions\Discounts\Offer\VolGr\UpdateVolumeGrOfferFromMaster;
use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Actions\Web\Webpage\CloseDiscontinuedWebpage;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Actions\Web\Webpage\ReopenDiscontinuedWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Web\Webpage;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductCategory extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithImageCatalogue;
    use WithProductCategoryHydrators;
    use SanitizeInputs;

    private ProductCategory $productCategory;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $originalImageId = $productCategory->image_id;
        $oldState        = $productCategory->state;

        if ($productCategory->type !== ProductCategoryTypeEnum::FAMILY) {
            Arr::pull($modelData, 'trade_unit_family_id'); // Safeguard so only family would have relationship with TradeUnitFamilyId
        }

        if ($chosenMainWebpageId = Arr::pull($modelData, 'set_main_webpage')) {
            $webpage = Webpage::find($chosenMainWebpageId);

            data_set($modelData, 'url', $webpage->url);
        }

        if (Arr::has($modelData, 'department_id')) {
            $departmentId = Arr::pull($modelData, 'department_id');
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $productCategory = UpdateFamilyDepartment::make()->action($productCategory, [
                    'department_id' => $departmentId,
                ]);
            } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $productCategory = UpdateSubDepartmentDepartment::make()->action($productCategory, [
                    'department_id' => $departmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'sub_department_id') && data_get($modelData, 'sub_department_id')) { // Null handling. If the key is present but the value is null, it will be ignored. HELP-1083 fix.
            $subDepartmentId = Arr::pull($modelData, 'sub_department_id');
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $productCategory = UpdateFamilySubDepartment::make()->action($productCategory, [
                    'sub_department_id' => $subDepartmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')];
            if ($imageData['image']) {
                $this->processCatalogueImage($imageData, $productCategory);
            } else {
                data_set($modelData, 'image_id', null, false);
            }
        }

        $originalMasterProductCategory = null;
        if (Arr::has($modelData, 'master_product_category_id')) {
            $originalMasterProductCategory = $productCategory->masterProductCategory;
        }

        if (Arr::has($modelData, 'vol_gr_offer')) {
            $volGrData = Arr::pull($modelData, 'vol_gr_offer');
            if ($volGrData) {
                $this->updateFamilyGrOffer($productCategory, $volGrData);
            } else {
                $this->finishFamilyGrOffer($productCategory);
            }
        }

        $productCategory = $this->update($productCategory, $modelData, ['data']);
        $productCategory->refresh();


        if (!$productCategory->image_id && $originalImageId) {
            $productCategory->images()->detach($originalImageId);
        }

        $changes = Arr::except($productCategory->getChanges(), ['updated_at']);

        if (Arr::has($changes, 'state')) {
            $this->productCategoryHydrators($productCategory);
        }

        if (Arr::has($changes, 'image_id')) {
            UpdateProductCategoryWebImages::run($productCategory);
        }

        if (Arr::hasAny($changes, ['type', 'state', 'master_product_category_id'])) {
            $this->masterProductCategoryUsageHydrators($productCategory, $productCategory->masterProductCategory);
            if ($originalMasterProductCategory != null && $originalMasterProductCategory->id != $productCategory->master_product_category_id) {
                $this->masterProductCategoryUsageHydrators($productCategory, $originalMasterProductCategory);
            }
        }

        if (Arr::has($changes, 'name')) {
            UpdateProductCategoryAndMasterTranslations::make()->action($productCategory, [
                'translations' => [
                    'name' => [$productCategory->shop->language->code => Arr::pull($modelData, 'name')]
                ]
            ]);
        }

        if (Arr::has($changes, 'description_title')) {
            UpdateProductCategoryAndMasterTranslations::make()->action($productCategory, [
                'translations' => [
                    'description_title' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description_title')]
                ]
            ]);
        }

        if (Arr::has($changes, 'description')) {
            UpdateProductCategoryAndMasterTranslations::make()->action($productCategory, [
                'translations' => [
                    'description' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description')]
                ]
            ]);
        }

        if (Arr::has($changes, 'description_extra')) {
            UpdateProductCategoryAndMasterTranslations::make()->action($productCategory, [
                'translations' => [
                    'description_extra' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description_extra')]
                ]
            ]);
        }

        if (Arr::has($changes, 'not_follow_master_prices') && !$productCategory->not_follow_master_prices) {
            ProductHydratePricesFromMaster::dispatch($productCategory);
        }

        if (Arr::hasAny($changes, [
            'code',
            'name',
            'type',
            'state',
            'name_i8n'
        ])) {
            $this->productCategoryHydrators($productCategory);

            if ($productCategory->webpage_id) {
                ReindexWebpageLuigiData::dispatch($productCategory->webpage->id)->delay(60);
                ClearCacheByWildcard::run("irisData:website:{$productCategory->webpage->website_id}:*");
            }
        }

        if (Arr::has($changes, 'follow_master_gr') && $productCategory->follow_master_gr && $productCategory->masterProductCategory) {
            UpdateVolumeGrOfferFromMaster::run($productCategory->masterProductCategory);
        }

        if ($oldState != $productCategory->state && $productCategory->webpage) {
            if ($productCategory->state == ProductCategoryStateEnum::DISCONTINUED) {
                CloseDiscontinuedWebpage::run($productCategory->webpage);
            }

            if ($productCategory->state == ProductCategoryStateEnum::ACTIVE && $oldState == ProductCategoryStateEnum::DISCONTINUED) {
                ReopenDiscontinuedWebpage::run($productCategory->webpage);
            }
        }

        $productCategory->refresh();

        return $productCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function prepareForValidation(): void
    {
        if ($this->has('department_or_sub_department_id')) {
            $parent = ProductCategory::find($this->get('department_or_sub_department_id'));
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $this->set('department_id', $parent->id);
                $this->set('sub_department_id', null);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $this->set('sub_department_id', $parent->id);
                $this->set('department_id', $parent->department->id);
            }
        }
    }


    public function rules(): array
    {
        $rules = [
            'code'                          => [
                'sometimes',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'type', 'value' => $this->productCategory->type, 'operator' => '='],
                        ['column' => 'id', 'value' => $this->productCategory->id, 'operator' => '!='],
                        ['column' => 'deleted_at', 'operator' => 'null'],

                    ]
                ),
            ],
            'name'                          => ['sometimes', 'max:250', 'string'],
            'image_id'                      => ['sometimes', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'state'                         => ['sometimes', 'required', Rule::enum(ProductCategoryStateEnum::class)],
            'description'                   => ['sometimes', 'nullable', 'max:65500'],
            'description_title'             => ['sometimes', 'nullable', 'max:255'],
            'description_extra'             => ['sometimes', 'nullable', 'max:65500'],
            'department_id'                 => [
                'sometimes',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ],
            'sub_department_id'             => [
                'sometimes',
                'nullable',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ],
            'follow_master'                 => ['sometimes', 'boolean'],
            'follow_master_gr'              => ['sometimes', 'boolean'],
            'vol_gr_offer'                  => ['sometimes', 'nullable', 'array'],
            'vol_gr_offer.item_quantity'    => ['sometimes', 'integer', 'min:1'],
            'vol_gr_offer.percentage_off'   => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'image'                         => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'webpage_id'                    => ['sometimes', 'integer', 'nullable', Rule::exists('webpages', 'id')->where('shop_id', $this->shop->id)],
            'url'                           => ['sometimes', 'nullable', 'string', 'max:250'],
            'images'                        => ['sometimes', 'array'],
            'master_product_category_id'    => ['sometimes', 'integer', 'nullable', Rule::exists('master_product_categories', 'id')->where('master_shop_id', $this->shop->master_shop_id)],
            'cost_price_ratio'              => ['sometimes', 'numeric', 'min:0'],
            'name_i8n'                      => ['sometimes', 'array'],
            'description_title_i8n'         => ['sometimes', 'array'],
            'description_i8n'               => ['sometimes', 'array'],
            'description_extra_i8n'         => ['sometimes', 'array'],
            'is_name_reviewed'              => ['sometimes', 'boolean'],
            'is_description_title_reviewed' => ['sometimes', 'boolean'],
            'is_description_reviewed'       => ['sometimes', 'boolean'],
            'is_description_extra_reviewed' => ['sometimes', 'boolean'],
            'set_main_webpage'              => ['sometimes', 'string'],
            'trade_unit_family_id'          => ['sometimes', 'integer', 'exists:trade_unit_families,id'],
            'faq'                           => ['sometimes', 'array'],
            'faq.*.question'                => ['sometimes', 'nullable', 'string'],
            'faq.*.answer'                  => ['sometimes', 'nullable', 'string'],
            'faq.*.source_question'         => ['sometimes', 'nullable', 'string'],
            'faq.*.source_answer'           => ['sometimes', 'nullable', 'string'],
            'not_follow_master_prices'      => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules['source_department_id'] = ['sometimes', 'string', 'max:255'];
            $rules['source_family_id']     = ['sometimes', 'string', 'max:255'];
            $rules                         = $this->noStrictUpdateRules($rules);
        }

        if (!$this->asAction && $this->productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            // Hard limit for Family (To accommodate design) if it's via UI update
            $rules['description']       = [
                'sometimes',
                'nullable',
                function ($value, $fail) {
                    $count = count(explode(' ', str_replace("&nbsp;", ' ', trim($this->sanitizeValue($value)))));
                    if ($count > 100) {
                        $fail(__("The description must not exceed 100 words."));
                    }
                }
            ];
            $rules['description_extra'] = [
                'sometimes',
                'nullable',
                function ($value, $fail) {
                    $count = count(explode(' ', str_replace("&nbsp;", ' ', trim($this->sanitizeValue($value)))));
                    if ($count > 250) {
                        $fail(__("The description extra must not exceed 250 words."));
                    }
                }
            ];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    private function updateFamilyGrOffer(ProductCategory $productCategory, ?array $volGrData): void
    {
        if (!$volGrData || empty($volGrData['item_quantity']) || empty($volGrData['percentage_off'])) {
            $productCategory->updateQuietly(['has_gr_vol_discount' => false]);

            return;
        }

        $itemQuantity  = (int)$volGrData['item_quantity'];
        $percentageOff = (float)$volGrData['percentage_off'];

        $offer = Offer::where('trigger_id', $productCategory->id)
            ->where('trigger_type', class_basename(ProductCategory::class))
            ->where('type', OfferTypeEnum::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL->value)
            ->with('offerAllowances')
            ->first();

        if (!$offer) {
            $offer = StoreVolumeGRDiscount::make()->action($productCategory, [
                'trigger_data_item_quantity' => $itemQuantity,
                'percentage_off'             => $percentageOff / 100,
                'interval'                   => 30,
            ]);
        } else {
            $triggerData = $offer->trigger_data;
            data_set($triggerData, 'item_quantity', $itemQuantity);

            $offer->update([
                'state'        => OfferStateEnum::ACTIVE,
                'status'       => true,
                'trigger_data' => $triggerData,
            ]);

            foreach ($offer->offerAllowances as $offerAllowance) {
                $allowanceData = $offerAllowance->data;
                data_set($allowanceData, 'percentage_off', $percentageOff / 100);

                $offerAllowance->update([
                    'state'  => $offer->state->value,
                    'status' => $offer->status,
                    'data'   => $allowanceData,
                    'end_at' => null,
                ]);
            }

            UpdateOfferAllowanceSignature::run($offer);
        }

        $productCategory->updateQuietly(['has_gr_vol_discount' => true]);

        if ($offer) {
            $offer->refresh();
            UpdateProductCategoryOffersData::run($offer);
            if ($productCategory->webpage) {
                BreakWebpageCache::dispatch($productCategory->webpage, true);
            }
        }

    }

    private function finishFamilyGrOffer(ProductCategory $productCategory): void
    {
        $offer = Offer::where('trigger_id', $productCategory->id)
            ->where('trigger_type', class_basename(ProductCategory::class))
            ->where('type', OfferTypeEnum::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL->value)
            ->with('offerAllowances')
            ->first();

        if ($offer) {
            FinishOffer::run($offer);
        }

        $productCategory->updateQuietly(['has_gr_vol_discount' => false]);



    }

    public function action(ProductCategory $productCategory, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): ProductCategory
    {
        if (!$audit) {
            ProductCategory::disableAuditing();
        }
        $this->asAction        = true;
        $this->productCategory = $productCategory;
        $this->hydratorsDelay  = $hydratorsDelay;
        $this->strict          = $strict;
        $this->initialisationFromShop($productCategory->shop, $modelData);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;

        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }


    public function jsonResponse(ProductCategory $productCategory): DepartmentsResource|SubDepartmentResource|FamilyResource
    {
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            return new DepartmentsResource($productCategory);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return new SubDepartmentResource($productCategory);
        } else {
            return new FamilyResource($productCategory);
        }
    }
}
