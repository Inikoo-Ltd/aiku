<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\GrpAction;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class EditMasterProduct extends GrpAction
{
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function inGroup(MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterAsset $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Master product'),
                'warning'     => $masterAsset->products ? [
                    'type'  => 'warning',
                    'title' => 'warning',
                    'text'  => __('Changing name or description may affect multiple products in various shops.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'model'   => __('Editing master product'),
                    'title'   => $masterAsset->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Master product'),
                        ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => $this->getBlueprint($masterAsset),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_asset.update',
                            'parameters' => [
                                'masterAsset' => $masterAsset->id
                            ]
                        ],
                    ]
                ]

            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function getBlueprint(MasterAsset $masterProduct): array
    {
        $barcodes = $masterProduct->tradeUnits->pluck('barcode')->filter()->unique();
        $packedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $masterProduct->tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        $tradeUnits = $masterProduct->tradeUnits->map(function ($t) use ($packedIn) {
            return array_merge(
                ['quantity' => (int)$t->pivot->quantity],
                ['fraction' => $t->pivot->quantity / $packedIn[$t->id]],
                ['packed_in' => $packedIn[$t->id]],
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($t->pivot->quantity / $packedIn[$t->id])), $packedIn[$t->id])],
                $t->toArray()
            );
        });

        return [
            [
                'label'  => __('Id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'code' => [
                        'type'  => 'input',
                        'label' => __('code'),
                        'value' => $masterProduct->code
                    ],
                ]
            ],
            [
                'label'  => __('Name/Description'),
                'icon'   => 'fa-light fa-tag',
                'fields' => [
                    'name'              => [
                        'type'    => 'input',
                        'label'   => __('Name'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->name
                    ],
                    'description_title' => [
                        'type'    => 'input',
                        'label'   => __('Description title'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description_title
                    ],
                    'description'       => [
                        'type'    => 'textEditor',
                        'label'   => __('Description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description
                    ],
                    'description_extra' => [
                        'type'    => 'textEditor',
                        'label'   => __('Extra description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description_extra
                    ],
                ]
            ],
            [
                'label'  => __('Translations'),
                'icon'   => 'fa-light fa-language',
                'fields' => [
                    'name_i8n'              => [
                        'type'          => 'input_translation',
                        'label'         => __('translate name'),
                        'language_from' => 'en',
                        'full'          => true,
                        'languages'     => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main'          => $masterProduct->name,
                        'value'         => $masterProduct->getTranslations('name_i8n')
                    ],
                    'description_title_i8n' => [
                        'type'          => 'input_translation',
                        'label'         => __('translate description title'),
                        'main'          => $masterProduct->description_title,
                        'languages'     => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'value'         => $masterProduct->getTranslations('description_title_i8n'),
                        'language_from' => 'en',
                        'full'          => true,
                    ],
                    'description_i8n'       => [
                        'type'          => 'textEditor_translation',
                        'label'         => __('translate description'),
                        'languages'     => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main'          => $masterProduct->description,
                        'language_from' => 'en',
                        'full'          => true,
                        'value'         => $masterProduct->getTranslations('description_i8n')
                    ],
                    'description_extra_i8n' => [
                        'type'          => 'textEditor_translation',
                        'language_from' => 'en',
                        'full'          => true,
                        'label'         => __('translate description extra'),
                        'languages'     => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main'          => $masterProduct->description_extra,
                        'value'         => $masterProduct->getTranslations('description_extra_i8n')
                    ],
                ]
            ],
            [
                'label'  => __('Properties'),
                'title'  => __('id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'unit'    => [
                        'type'  => 'input',
                        'label' => __('unit'),
                        'value' => $masterProduct->unit,
                    ],
                    'barcode' => [
                        'type'     => 'select',
                        'label'    => __('barcode'),
                        'value'    => $masterProduct->barcode,
                        'readonly' => $masterProduct->tradeUnits->count() == 1,
                        'options'  => $barcodes->mapWithKeys(function ($barcode) {
                            return [$barcode => $barcode];
                        })->toArray()
                    ],

                ]
            ],
            [
                'label'  => __('Master Family'),
                'icon'   => 'fal fa-folder',
                'fields' => [
                    'master_family_id' => [
                        'type'       => 'select_infinite',
                        'label'      => __('Master Family'),
                        'options'    => [
                            MasterFamiliesResource::make($masterProduct->masterFamily)->toArray(request())
                        ],
                        'fetchRoute' => [
                            'name'       => 'grp.json.master-family.all-master-family',
                            'parameters' => [
                                'masterShop'                    => $masterProduct->masterShop->slug,
                                'withMasterProductCategoryStat' => true,
                            ]
                        ],
                        'required'   => true,
                        'type_label' => 'families',
                        'valueProp'  => 'id',
                        'labelProp'  => 'code',
                        'value'      => $masterProduct->master_family_id,
                    ]
                ]
            ],

            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'        => __('Trade Units'),
                        'type'         => 'list-selector-trade-unit',
                        'key_quantity' => 'quantity',
                        'withQuantity' => true,
                        'full'         => true,
                        'is_dropship'  => $masterProduct->masterShop->type == ShopTypeEnum::DROPSHIPPING,
                        'tabs'         => [
                            [
                                'label'      => __('To do'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.recommended-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->slug,
                                    ],
                                ],
                            ],
                            [
                                'label'      => __('Done'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.taken-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->slug,
                                    ],
                                ],
                            ],
                            [
                                'label'      => __('All'),
                                'search'     => true,
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.all-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->slug,
                                    ],
                                ],
                            ],
                        ],
                        'value'        => $tradeUnits,
                    ],
                ],
            ],

            $masterProduct->not_for_sale_from_trade_unit
                ? []
                : [
                'label'  => __('Sale Status'),
                'icon'   => 'fal fa-cart-arrow-down',
                'fields' => [
                    'is_for_sale' => [
                        'confirmation' => [
                            'description' => __('Changing the sale status of a master product will affect all products linked to it in all shops.'),
                        ],
                        'type'         => 'toggle',
                        'label'        => __('For Sale'),
                        'value'        => $masterProduct->is_for_sale,
                    ],
                ],
            ],

        ];
    }


    public function getBreadcrumbs(MasterAsset $masterAsset, string $routeName, array $routeParameters): array
    {
        return ShowMasterProduct::make()->getBreadcrumbs(
            masterAsset: $masterAsset,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
