<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Product\Traits\WithPreOrderEditFields;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\FamilyCustomizeEnum;
use App\Enums\Catalogue\ProductCategory\FamilyStorageConditionEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterFamily extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterFamilyNavigation;
    use WithPreOrderEditFields;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        $masterShop = $masterProductCategory->masterShop;

        $iconLinks = [];

        if ($masterProductCategory->tradeUnitFamily && $request->user()->authTo('goods.edit')) {
            $iconLinks[] = [
                'icon'    => 'fal fa-atom-alt',
                'tooltip' => __('Go to Edit Trade Unit Family'),
                'route'   => [
                    'name'       => 'grp.trade_units.families.edit',
                    'parameters' => [
                        $masterProductCategory->tradeUnitFamily->slug
                    ]
                ],
            ];
        }

        $isAromaShop = $masterShop->slug == 'aroma';

        return Inertia::render(
            'Masters/EditMasterFamily',
            [
                'warning'      => $masterProductCategory->productCategories ? __('Changes to this master name or descriptions will overwrite child product names and descriptions where “Follow Master” is enabled.') : null,
                'breadcrumbs'  => $this->getBreadcrumbs(
                    $masterProductCategory,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'   => [
                    'previous' => $this->getPreviousModel($masterProductCategory, $request),
                    'next'     => $this->getNextModel($masterProductCategory, $request),
                ],
                'title'        => __('Edit Master Family'),
                'pageHead'     => [
                    'title'     => __('Edit master family'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                    'iconLinks' => $iconLinks,
                ],
                'masterFamily' => [
                    'code'                     => $masterProductCategory->code,
                    'name'                     => $masterProductCategory->name,
                    'description_title'        => $masterProductCategory->description_title,
                    'description'              => $masterProductCategory->description,
                    'description_extra'        => $masterProductCategory->description_extra,
                    'master_department_id'     => $masterProductCategory->master_department_id,
                    'master_sub_department_id' => $masterProductCategory->master_sub_department_id,
                    'master_collection_ids'    => MasterCollection::whereHas('masterFamilies', fn ($query) => $query->where('master_product_categories.id', $masterProductCategory->id))->pluck('id'),
                    'trade_unit_family'        => $masterProductCategory->tradeUnitFamily
                        ? $masterProductCategory->tradeUnitFamily->code.' ('.$masterProductCategory->tradeUnitFamily->name.')'
                        : null,
                    'faq'                      => $masterProductCategory->faq,
                    'meta_description'         => $masterProductCategory->meta_description,
                    'seo_image'                => $masterProductCategory->seo_image_url
                        ? ['original' => $masterProductCategory->seo_image_url]
                        : $masterProductCategory->imageSources(1200, 1200, 'seoImage'),
                    'seo_image_url'            => $masterProductCategory->seo_image_url,
                    'seo_image_alt'            => $masterProductCategory->seo_image_alt,
                    'vol_gr_offer'             => [
                        'item_quantity'  => (int)$masterProductCategory->gr_vol_discount_quantity,
                        'percentage_off' => (float)trimDecimalZeros($masterProductCategory->gr_vol_discount_percentage),
                    ],
                ],
                'departments'          => $masterShop->masterProductCategories()
                    ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'subDepartments'       => $masterShop->masterProductCategories()
                    ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
                    ->orderBy('name')
                    ->get(['id', 'name', 'master_department_id']),
                'masterCollections'    => MasterCollection::where('master_shop_id', $masterShop->id)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'isGoldRewardEligible' => (bool)$masterShop->gold_reward_eligible,
                'faqField'             => [
                    'master_product_category_id' => $masterProductCategory->id,
                ],
                'extraSections'        => [
                    $this->preOrderEditFieldsSection(
                        $this->sharedPreOrderValues($masterProductCategory),
                        __('Saving applies it to all :count master products in this family and their shop products. Shown off or empty when the products differ.', ['count' => $masterProductCategory->masterAssets()->count()])
                    ),
                    ...($isAromaShop ? [
                    [
                        'label'  => __('Customize'),
                        'fields' => [
                            'customize_option' => [
                                'type'                       => 'family_customize',
                                'label'                      => __('Customize'),
                                'options'                    => FamilyCustomizeEnum::valuesWithLabelsAndIcons(),
                                'value'                      => FamilyCustomizeEnum::rows($masterProductCategory->customize_option),
                                'master_product_category_id' => $masterProductCategory->id,
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Storage & Shelf Life'),
                        'fields' => [
                            'storage_conditions'  => [
                                'type'                       => 'family_storage_conditions',
                                'label'                      => __('Storage table'),
                                'options'                    => FamilyStorageConditionEnum::valuesWithLabelsAndPlaceholders(),
                                'value'                      => FamilyStorageConditionEnum::rows(data_get($masterProductCategory->storage_option, 'storage_conditions', [])),
                                'master_product_category_id' => $masterProductCategory->id,
                            ],
                            'storage_temperature' => [
                                'type'        => 'input',
                                'label'       => __('Storage temperature'),
                                'placeholder' => __('e.g. 15°C - 25°C'),
                                'value'       => data_get($masterProductCategory->storage_option, 'storage_temperature', ''),
                            ],
                            'storage_guidelines'  => [
                                'type'     => 'dynamic_list',
                                'label'    => __('Storage guidelines'),
                                'value'    => data_get($masterProductCategory->storage_option, 'storage_guidelines', []),
                                'fields'   => [
                                    ['key' => 'text', 'placeholder' => __('e.g. Keep products in their original packaging.')],
                                ],
                                'addLabel' => __('Add guideline'),
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Category Comparison'),
                        'fields' => [
                            'category_comparison' => [
                                'full'  => true,
                                'type'  => 'category-comparison',
                                'label' => __('Category Comparison'),
                                'value' => $masterProductCategory->category_comparison,
                            ],
                        ],
                    ],
                ] : []),
                ],
                'completeness'         => GetMasterFamilyCompleteness::run($masterProductCategory),
                'shopsCount'           => GetMasterFamilyShopsStatus::run($masterProductCategory)['total'],
                'familiesRoute'        => [
                    'name'       => preg_replace('/edit$/', 'families', $request->route()->getName()),
                    'parameters' => $request->route()->originalParameters(),
                ],
                'updateRoute'          => [
                    'name'       => 'grp.models.master_product_category.update',
                    'parameters' => [
                        'masterProductCategory' => $masterProductCategory->id
                    ]
                ],
            ]
        );
    }


    /**
     * @return array<string, mixed>
     */
    private function sharedPreOrderValues(MasterProductCategory $masterFamily): array
    {
        $masterAssets = $masterFamily->masterAssets()->get(Product::PRE_ORDER_FIELDS);

        $values = [
            'is_back_order'    => $masterAssets->isNotEmpty() && $masterAssets->every('is_back_order'),
            'is_made_to_order' => $masterAssets->isNotEmpty() && $masterAssets->every('is_made_to_order'),
        ];
        foreach (['pre_order_lead_time_days', 'pre_order_deposit_percentage', 'max_quantity_per_order'] as $field) {
            $distinctValues = $masterAssets->pluck($field)->unique();
            $values[$field] = $distinctValues->count() === 1 ? $distinctValues->first() : null;
        }

        return $values;
    }

    public function getBreadcrumbs(MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs(
            masterFamily: $masterFamily,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
