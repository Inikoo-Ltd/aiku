<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Discounts\Offer\UpdateVolumeGrOfferFromMaster;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterSubDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateTradeUnitFamilyToChildFamily;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateFAQ;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterSubDepartments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterProductCategories;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdateMasterProductCategory extends OrgAction
{
    use WithImageCatalogue;
    use WithMasterProductCategoryAction;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        if ($masterProductCategory->type !== MasterProductCategoryTypeEnum::FAMILY) {
            Arr::pull($modelData, 'trade_unit_family_id'); // Safe guard so only family would have relationship with TradeUnitFamilyId
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

        if (Arr::has($modelData, 'vol_gr_offer')) {
            $volGR = Arr::pull($modelData, 'vol_gr_offer');

            if ($volGR) {
                data_set($modelData, 'has_gr_vol_discount', true);

                data_set($modelData, 'gr_vol_discount_percentage', $volGR['percentage_off']);
                data_set($modelData, 'gr_vol_discount_quantity', $volGR['item_quantity']);
            } else {
                data_set($modelData, 'has_gr_vol_discount', false);
                // TODO: Delete GR Reward from Master Product Category to Children
            }
        }

        $masterProductCategory = $this->update($masterProductCategory, $modelData, ['data']);

        $changed = Arr::except($masterProductCategory->getChanges(), ['updated_at']);

        if (Arr::hasAny($changed, ['gr_vol_discount_percentage', 'gr_vol_discount_quantity'])) {
            $result = UpdateVolumeGrOfferFromMaster::make()->action($masterProductCategory);

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

        if (Arr::hasAny($changed, ['name', 'description', 'description_title', 'description_extra', 'code'])) {

            $english      = Language::where('code', 'en')->first();

            foreach ($masterProductCategory->productCategories as $productCategory) {
                $shop = $productCategory->shop;
                if (!data_get($shop->settings, "catalog.{$productCategory->type->value}_follow_master")) {
                    continue;
                }

                $shopLanguage = $shop->language;
                $dataToBeUpdated = [];

                // Updates the affected field name using translation if follow_master_{field} is true
                if (Arr::has($changed, 'name')) {
                    $dataToBeUpdated['name'] = Translate::run($masterProductCategory->name, $english, $shopLanguage);
                    $dataToBeUpdated['is_name_reviewed'] = false;
                }

                if (Arr::has($changed, 'description_title')) {
                    $dataToBeUpdated['description_title'] = Translate::run($masterProductCategory->description_title, $english, $shopLanguage);
                    $dataToBeUpdated['is_description_title_reviewed'] = false;
                }

                if (Arr::has($changed, 'description')) {
                    $dataToBeUpdated['description'] = Translate::run($masterProductCategory->description, $english, $shopLanguage);
                    $dataToBeUpdated['is_description_reviewed'] = false;
                }

                if (Arr::has($changed, 'description_extra')) {
                    $dataToBeUpdated['description_extra'] = Translate::run($masterProductCategory->description_extra, $english, $shopLanguage);
                    $dataToBeUpdated['is_description_extra_reviewed'] = false;
                }

                if (Arr::has($changed, 'code')) {
                    $dataToBeUpdated['code'] = $masterProductCategory->code;
                }

                if ($dataToBeUpdated) {
                    UpdateProductCategory::make()->action($productCategory, $dataToBeUpdated);
                }
            }
        }

        $masterProductCategory->refresh();

        if (!$masterProductCategory->image_id && $originalImageId) {
            $masterProductCategory->images()->detach($originalImageId);
        }

        if ($masterProductCategory->wasChanged('trade_unit_family_id')) {
            MasterFamilyHydrateTradeUnitFamilyToChildFamily::make()->action($masterProductCategory);
        }

        if ($masterProductCategory->wasChanged('faq')) {
            MasterProductCategoryHydrateFAQ::make()->action($masterProductCategory);
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
            'cost_price_ratio'              => ['sometimes', 'numeric', 'min:0'],
            'trade_unit_family_id'          => ['sometimes', 'integer', 'exists:trade_unit_families,id'],
            'faq'                           => ['sometimes', 'array'],
            'faq.*.question'                => ['sometimes', 'string'],
            'faq.*.answer'                  => ['sometimes', 'string'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        if (!$this->asAction && $this->masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            // Hard limit for Master Family (To accomodate design) if it's via UI update
            $rules['description']       = ['sometimes', 'nullable', 'max:400'];
            $rules['description_extra'] = ['sometimes', 'nullable', 'max:1250'];
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
