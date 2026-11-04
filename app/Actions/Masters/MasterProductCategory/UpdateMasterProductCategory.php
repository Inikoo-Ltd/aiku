<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Discounts\Offer\VolGr\FinishVolumeGrOfferFromMaster;
use App\Actions\Discounts\Offer\VolGr\UpdateVolumeGrOfferFromMaster;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterCollection\AttachModelToMasterCollection;
use App\Actions\Masters\MasterCollection\DetachMasterModelFromMasterCollection;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterSubDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamiliesWithVolGrDiscount;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterSubDepartments;
use App\Actions\Masters\WithMasterSeoImage;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterProductCategories;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Rules\MaxPlainTextLength;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdateMasterProductCategory extends OrgAction
{
    use WithImageCatalogue;
    use WithMasterSeoImage;
    use WithMasterProductCategoryAction;
    use SanitizeInputs;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        if (Arr::hasAny($modelData, ['storage_conditions', 'storage_temperature', 'storage_guidelines'])) {
            $storageOption = $masterProductCategory->storage_option;

            if (Arr::has($modelData, 'storage_conditions')) {
                data_set($storageOption, 'storage_conditions', Arr::pull($modelData, 'storage_conditions'));
            }

            if (Arr::has($modelData, 'storage_temperature')) {
                data_set($storageOption, 'storage_temperature', Arr::pull($modelData, 'storage_temperature'));
            }

            if (Arr::has($modelData, 'storage_guidelines')) {
                data_set($storageOption, 'storage_guidelines', Arr::pull($modelData, 'storage_guidelines'));
            }

            data_set($modelData, 'storage_option', $storageOption);
        }

        $originalImageId = $masterProductCategory->image_id;
        if (Arr::has($modelData, 'master_department_id')) {
            $departmentId = Arr::pull($modelData, 'master_department_id');
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                $masterProductCategory = UpdateMasterFamilyMasterDepartment::make()->action($masterProductCategory, [
                    'master_department_id' => $departmentId,
                ]);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $masterProductCategory = UpdateMasterSubDepartmentMasterDepartment::make()->action($masterProductCategory, [
                    'master_department_id' => $departmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'master_sub_department_id')) {
            $subDepartmentId = Arr::pull($modelData, 'master_sub_department_id');
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                $masterProductCategory = UpdateMasterFamilyMasterSubDepartment::make()->action($masterProductCategory, [
                    'master_sub_department_id' => $subDepartmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')];
            if ($imageData['image']) {
                $this->processCatalogueImage($imageData, $masterProductCategory);
            } else {
                data_set($modelData, 'image_id', null, false);
            }
        }

        if (Arr::has($modelData, 'seo_image_url')) {
            $modelData['seo_image_url'] = filled($modelData['seo_image_url']) ? $modelData['seo_image_url'] : null;
        }

        $seoImage = Arr::pull($modelData, 'seo_image');
        if ($seoImage) {
            $modelData['seo_image_url'] = null;
            $masterProductCategory      = $this->processMasterSeoImage($seoImage, $masterProductCategory);
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n'),
                ],
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n'),
                ],
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n'),
                ],
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n'),
                ],
            ]);
        }

        $grUpdating = false;
        $grDeleting = false;
        if (Arr::has($modelData, 'vol_gr_offer')) {
            $volGR      = Arr::pull($modelData, 'vol_gr_offer');
            if ($volGR) {
                data_set($modelData, 'has_gr_vol_discount', true);
                data_set($modelData, 'gr_vol_discount_percentage', $volGR['percentage_off']);
                data_set($modelData, 'gr_vol_discount_quantity', $volGR['item_quantity']);
                $grUpdating = true;
            } else {
                data_set($modelData, 'has_gr_vol_discount', false);
                data_set($modelData, 'gr_vol_discount_percentage', 0);
                data_set($modelData, 'gr_vol_discount_quantity', 0);
                $grDeleting = true;
            }
        }

        $masterCollectionIds = Arr::pull($modelData, 'master_collection_ids');

        $preOrderData = Arr::only($modelData, Product::PRE_ORDER_FIELDS);
        $modelData    = Arr::except($modelData, Product::PRE_ORDER_FIELDS);

        $masterProductCategory = $this->update($masterProductCategory, $modelData, ['data']);

        if ($masterCollectionIds !== null) {
            $this->syncMasterCollections($masterProductCategory, $masterCollectionIds);
        }

        if ($preOrderData && $masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            foreach ($masterProductCategory->masterAssets()->get() as $masterAsset) {
                UpdateMasterAsset::make()->action($masterAsset, $preOrderData);
            }
        }

        $changed = Arr::except($masterProductCategory->getChanges(), ['updated_at']);


        if ($grDeleting) {
            FinishVolumeGrOfferFromMaster::dispatch($masterProductCategory);
        }

        if ($grUpdating && Arr::hasAny($changed, ['gr_vol_discount_percentage', 'gr_vol_discount_quantity'])) {
            $result = UpdateVolumeGrOfferFromMaster::run($masterProductCategory);

            if (data_get($result, 'updated_offers', 0) == 0) {

                session()->flash('notification', [
                    'status'      => 'warning',
                    'title'       => __('Warning'),
                    'description' => data_get($result, 'error_message', __('No offers found to update for this master family.')),
                ]);
            } else {
                session()->flash('notification', [
                    'status'      => 'success',
                    'title'       => __('Success'),
                    'description' => __('Updated :__offerCount offers and :__allowanceCount allowances.', [
                        '__offerCount' => data_get($result, 'updated_offers', 0),
                        '__allowanceCount' => data_get($result, 'updated_allowances', 0),
                    ]),
                ]);
            }
        }

        if (Arr::has($changed, 'faq')) {
            CascadeMasterProductCategoryFaqToChildren::dispatch($masterProductCategory);
        }

        if (Arr::has($changed, 'customize_option')) {
            CascadeMasterProductCategoryCustomizeOptionToChildren::run($masterProductCategory); // TODO: change to dispatch later
        }

        if (Arr::has($changed, 'storage_option')) {
            CascadeMasterProductCategoryStorageOptionToChildren::run($masterProductCategory); // TODO: change to dispatch later
        }

        $seoImageChanged = $seoImage || Arr::has($changed, 'seo_image_url');

        if ($seoImageChanged || Arr::hasAny($changed, ['name', 'description', 'description_title', 'description_extra', 'code', 'category_comparison', 'meta_description', 'seo_image_alt'])) {

            $english      = Language::where('code', 'en')->first();

            foreach ($masterProductCategory->productCategories as $productCategory) {
                $shop = $productCategory->shop;

                $followMaster = data_get($shop->settings, "catalog.{$productCategory->type->value}_follow_master");

                $shopLanguage = $shop->language;
                $brief = GetCatalogueTranslationBrief::run($shopLanguage, $productCategory);
                $dataToBeUpdated = [];

                // Updates the affected field name using translation if follow_master_{field} is true
                // Regardless, update is_x_reviewed to false when master is updated, to make it easier to track changes
                if (Arr::has($changed, 'name')) {
                    if ($followMaster) {
                        $dataToBeUpdated['name'] = Translate::run($masterProductCategory->name, $english, $shopLanguage, 'catalogue', brief: $brief);
                    }
                    $dataToBeUpdated['is_name_reviewed'] = false;
                }

                if (Arr::has($changed, 'description_title')) {
                    if ($followMaster) {
                        $dataToBeUpdated['description_title'] = Translate::run($masterProductCategory->description_title, $english, $shopLanguage, 'catalogue', brief: $brief);
                    }
                    $dataToBeUpdated['is_description_title_reviewed'] = false;
                }

                if (Arr::has($changed, 'description')) {
                    if ($followMaster) {
                        $dataToBeUpdated['description'] = Translate::run($masterProductCategory->description, $english, $shopLanguage, 'catalogue', brief: $brief);
                    }
                    $dataToBeUpdated['is_description_reviewed'] = false;
                }

                if (Arr::has($changed, 'description_extra')) {
                    if ($followMaster) {
                        $dataToBeUpdated['description_extra'] = Translate::run($masterProductCategory->description_extra, $english, $shopLanguage, 'catalogue', brief: $brief);
                    }
                    $dataToBeUpdated['is_description_extra_reviewed'] = false;
                }

                if (Arr::has($changed, 'category_comparison')) {
                    if ($followMaster) {
                        $translatedCategoryComparison = Translate::run(json_encode($masterProductCategory->category_comparison), $english, $shopLanguage, 'catalogue', brief: $brief);
                        if (is_string($translatedCategoryComparison)) {
                            $translatedCategoryComparison = json_decode($translatedCategoryComparison, true);
                        }
                        $dataToBeUpdated['category_comparison'] = $translatedCategoryComparison;
                    }
                }

                if (Arr::has($changed, 'code')) {
                    $dataToBeUpdated['code'] = $masterProductCategory->code;
                }

                if ($dataToBeUpdated) {
                    UpdateProductCategory::make()->action($productCategory, $dataToBeUpdated);
                }

                $webpageData = [];
                if ($followMaster && $productCategory->webpage) {
                    if (Arr::has($changed, 'meta_description')) {
                        $webpageData['description'] = Translate::run($masterProductCategory->meta_description, $english, $shopLanguage, 'catalogue', brief: $brief);
                    }

                    if (Arr::has($changed, 'seo_image_alt')) {
                        $webpageData['seo_image_alt'] = filled($masterProductCategory->seo_image_alt)
                            ? Translate::run($masterProductCategory->seo_image_alt, $english, $shopLanguage, 'catalogue', brief: $brief)
                            : null;
                    }

                    if ($seoImageChanged) {
                        $webpageData['seo_image_id']  = $masterProductCategory->seo_image_id;
                        $webpageData['seo_image_url'] = $masterProductCategory->seo_image_url;
                    }
                }

                if ($webpageData) {
                    UpdateWebpage::make()->action($productCategory->webpage, $webpageData);
                }
            }
        }

        $masterProductCategory->refresh();

        if (!$masterProductCategory->image_id && $originalImageId) {
            $masterProductCategory->images()->detach($originalImageId);
        }

        if ($masterProductCategory->wasChanged('has_gr_vol_discount')) {
            MasterShopHydrateMasterFamiliesWithVolGrDiscount::dispatch($masterProductCategory->masterShop);
        }


        if ($masterProductCategory->wasChanged('status')) {
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                MasterShopHydrateMasterDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                MasterShopHydrateMasterFamilies::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                MasterDepartmentHydrateMasterSubDepartments::dispatch($masterProductCategory->masterDepartment)->delay($this->hydratorsDelay);
                MasterShopHydrateMasterSubDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            }

            GroupHydrateMasterProductCategories::dispatch($masterProductCategory->group)->delay($this->hydratorsDelay);
        }

        return $masterProductCategory;
    }

    public function afterValidator(Validator $validator): void
    {
        $currErrBag = $validator->errors();
        if (errorBagHas($currErrBag, ['offers_data.volume_discount.item_quantity', 'offers_data.volume_discount.percentage_off'])) {
            session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error'),
                'description' => __('Failed to update offer details.'),
            ]);
        }
    }

    /**
     * The master collections a family is in, through the same attach and detach as the collection
     * page, so the shop collections that follow the master get the family too.
     *
     * @param  array<int, int>  $masterCollectionIds
     */
    private function syncMasterCollections(MasterProductCategory $masterFamily, array $masterCollectionIds): void
    {
        $currentIds = MasterCollection::whereHas('masterFamilies', fn ($query) => $query->where('master_product_categories.id', $masterFamily->id))->pluck('id')->all();

        foreach (MasterCollection::whereIn('id', array_diff($masterCollectionIds, $currentIds))->get() as $masterCollection) {
            AttachModelToMasterCollection::make()->action($masterCollection, $masterFamily);
        }

        foreach (MasterCollection::whereIn('id', array_diff($currentIds, $masterCollectionIds))->get() as $masterCollection) {
            DetachMasterModelFromMasterCollection::make()->action($masterCollection, $masterFamily);
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
                    table: 'master_product_categories',
                    extraConditions: [
                        ['column' => 'master_shop_id', 'value' => $this->masterShop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'type', 'value' => $this->masterProductCategory->type, 'operator' => '='],
                        ['column' => 'id', 'value' => $this->masterProductCategory->id, 'operator' => '!='],

                    ]
                ),
            ],
            'name'                          => ['sometimes', 'max:250', 'string'],
            'image_id'                      => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'status'                        => ['sometimes', 'required', 'boolean'],
            'description'                   => ['sometimes', 'max:65500'],
            'description_title'             => ['sometimes', 'nullable', 'max:255'],
            'description_extra'             => ['sometimes', 'nullable', 'max:65500'],
            'master_department_id'          => ['sometimes', 'nullable', 'exists:master_product_categories,id'],
            'master_sub_department_id'      => ['sometimes', 'nullable', 'exists:master_product_categories,id'],
            'show_in_website'               => ['sometimes', 'boolean'],
            'meta_description'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_image'                     => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024),
            ],
            'seo_image_url'                 => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'seo_image_alt'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'image'                         => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024),
            ],
            'name_i8n'                      => ['sometimes', 'array'],
            'description_title_i8n'         => ['sometimes', 'array'],
            'description_i8n'               => ['sometimes', 'array'],
            'description_extra_i8n'         => ['sometimes', 'array'],
            'vol_gr_offer'                  => ['nullable', 'array'],
            'vol_gr_offer.item_quantity'    => [
                'required_with:vol_gr_offer.percentage_off',
                'nullable',
                'integer',
                'min:1'
            ],
            'vol_gr_offer.percentage_off'   => [
                'required_with:vol_gr_offer.item_quantity',
                'nullable',
                'numeric',
                'min:1',
                'max:100'
            ],
            'is_back_order'                 => ['sometimes', 'boolean'],
            'is_made_to_order'              => ['sometimes', 'boolean'],
            'pre_order_deposit_percentage'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'pre_order_lead_time_days'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'max_quantity_per_order'        => ['sometimes', 'nullable', 'integer', 'min:1'],
            'master_collection_ids'         => ['sometimes', 'array'],
            'master_collection_ids.*'       => ['integer', Rule::exists('master_collections', 'id')->where('master_shop_id', $this->masterProductCategory->master_shop_id)],
            'faq'                           => ['sometimes', 'array'],
            'faq.*.question'                => ['sometimes', 'nullable', 'string'],
            'faq.*.answer'                  => ['sometimes', 'nullable', 'string'],
            // customize_option
            'customize_option'              => ['sometimes', 'array'],
            'customize_option.*'            => ['sometimes', 'array'],
            'customize_option.*.key'        => ['sometimes', 'nullable', 'string'],
            'customize_option.*.label'      => ['sometimes', 'nullable', 'string'],
            'customize_option.*.icon'       => ['sometimes', 'nullable', 'string'],
            'customize_option.*.available'  => ['sometimes', 'boolean'],
            'customize_option.*.moq'        => ['sometimes', 'nullable', 'string'],
            'customize_option.*.notes'      => ['sometimes', 'nullable', 'string', new MaxPlainTextLength(255)],
            // storage_option
            'storage_conditions'            => ['sometimes', 'array'],
            'storage_conditions.*.key'      => ['sometimes', 'nullable', 'string'],
            'storage_conditions.*.label'    => ['sometimes', 'nullable', 'string'],
            'storage_conditions.*.value'    => ['sometimes', 'nullable', 'string'],
            'storage_temperature'           => ['sometimes', 'nullable', 'string'],
            'storage_guidelines'            => ['sometimes', 'array'],
            'storage_guidelines.*.text'     => ['sometimes', 'string', 'max:250'],
            // category_comparison
            'category_comparison'                   => ['sometimes', 'array'],
            'category_comparison.template'          => ['sometimes', 'string'],
            'category_comparison.items'             => ['sometimes', 'array'],
            'category_comparison.items.*.show'      => ['sometimes', 'boolean', 'nullable'],
            'category_comparison.items.*.label'     => ['sometimes', 'string', 'nullable'],
            'category_comparison.items.*.value'     => ['sometimes', 'string'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        if (!$this->asAction && $this->masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            // Hard limit for Master Family (To accommodate design) if it's via UI update
            $rules['description']       = ['sometimes', 'nullable',  function ($value, $fail) {
                $count = count(explode(' ', str_replace("&nbsp;", ' ', trim($this->sanitizeValue($value)))));
                if ($count > 100) {
                    $fail(__("The description must not exceed 100 words."));
                }
            }];

            $rules['description_extra'] = ['sometimes', 'nullable', function ($value, $fail) {
                $count = count(explode(' ', str_replace("&nbsp;", ' ', trim($this->sanitizeValue($value)))));
                if ($count > 250) {
                    $fail(__("The description extra must not exceed 250 words."));
                }
            }];
        }

        return $rules;
    }

    public function getValidationMessages(): array
    {
        return [
            'vol_gr_offer.item_quantity.min'            => 'Item quantity must be bigger than 1',
            'vol_gr_offer.item_quantity.required_with'  => 'Item quantity must be be bigger than 1',
            'vol_gr_offer.percentage_off.min'           => 'Discount must be bigger than 1',
            'vol_gr_offer.percentage_off.required_with' => 'Discount must be bigger than 1',
        ];
    }
}
