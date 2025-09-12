<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Actions\GrpAction;
use App\Models\Catalogue\Shop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class EditMasterProduct extends GrpAction
{
    private MasterProductCategory $parent;

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

    public function inMasterDepartment(MasterAsset $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

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
                'title'       => __('master product'),
                'warning' => $masterAsset->products ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect multiple products in various shops.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $masterAsset->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('master product'),
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

                'formData'    => [
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
                    'name' => [
                        'type'  => 'input',
                        'label' => __('name'),
                        'value' => $masterProduct->name
                    ],
                    'description_title' => [
                        'type'  => 'input',
                        'label' => __('description title'),
                        'value' => $masterProduct->description_title
                    ],
                    'description' => [
                        'type'  => 'textEditor',
                        'label' => __('description'),
                        'value' => $masterProduct->description
                    ],
                    'description_extra' => [
                        'type'  => 'textEditor',
                        'label' => __('Extra description'),
                        'value' => $masterProduct->description_extra
                    ],
                ]
            ],
            [
                'label'  => __('Translations'),
                'icon'   => 'fa-light fa-language',
                'fields' => [
                    'name_i8n' => [
                        'type'  => 'input_translation',
                        'label' => __('translate name'),
                        'language_from' => 'en',
                        'full' => true,
                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main'  => $masterProduct->name,
                        'value' => $masterProduct->getTranslations('name_i8n')
                    ],
                    'description_title_i8n' => [
                        'type'  => 'input_translation',
                        'label' => __('translate description title'),
                        'main'  => $masterProduct->description_title,
                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'value' => $masterProduct->getTranslations('description_title_i8n'),
                        'language_from' => 'en',
                        'full' => true,
                    ],
                    'description_i8n' => [
                        'type'  => 'textEditor_translation',
                        'label' => __('translate description'),
                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main'  => $masterProduct->description,
                        'language_from' => 'en',
                        'full' => true,
                        'value' => $masterProduct->getTranslations('description_i8n')
                    ],
                    'description_extra_i8n' => [
                        'type'  => 'textEditor_translation',
                        'language_from' => 'en',
                        'full' => true,
                        'label' => __('translate description extra'),
                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProduct->group->extra_languages),
                        'main' => $masterProduct->description_extra,
                        'value' => $masterProduct->getTranslations('description_extra_i8n')
                    ],
                ]
            ],
            [
                'label'  => __('Pricing'),
                'icon'   => 'fa-light fa-money-bill',
                'fields' => [
                    'cost_price_ratio' => [
                        'type'          => 'input_number',
                        'bind' => [
                            'maxFractionDigits' => 3
                        ],
                        'label'         => __('pricing ratio'),
                        'placeholder'   => __('Cost price ratio'),
                        'required'      => true,
                        'value'         => $masterProduct->cost_price_ratio,
                        'min'           => 0
                    ],
                ]
            ],
            [
                'label'  => __('Properties'),
                'title'  => __('id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'unit'        => [
                        'type'  => 'input',
                        'label' => __('unit'),
                        'value' => $masterProduct->unit,
                    ],
                    'units'       => [
                        'type'  => 'input',
                        'label' => __('units'),
                        'value' => $masterProduct->units,
                    ],
                    'barcode'       => [
                        'type'  => 'select',
                        'label' => __('barcode'),
                        'value' => $masterProduct->barcode,
                        'readonly' => $masterProduct->tradeUnits->count() == 1,
                        'options' => $barcodes->mapWithKeys(function ($barcode) {
                            return [$barcode => $barcode];
                        })->toArray()
                    ],
                    'price'       => [
                        'type'     => 'input',
                        'label'    => __('price'),
                        'required' => true,
                        'value'    => $masterProduct->price
                    ],
                    'marketing_weight'       => [
                        'type'     => 'input_number',
                        'label'    => __('marketing weight'),
                        'value'    => $masterProduct->marketing_weight,
                        'bind'     =>[
                                'suffix' => 'g'
                        ]
                    ],
                ]
            ],
            [
                'label' => __('Trade unit'),
                'icon' => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'      => __('Trade Units'),
                        'type' => 'edit-trade-unit-shop',
                        'value' => null,
                        'noSaveButton' => true,
                        'trade_units' => $masterProduct->tradeUnits ? $this->getDataTradeUnit($masterProduct->tradeUnits) : []
                    ]
                ],
            ],
            // [
            //     'label'  => __('Family'),
            //     'icon'   => 'fa-light fa-folder',
            //     'fields' => [
            //         'family_id' => [
            //             'type'       => 'select_infinite',
            //             'label'      => __('Family'),
            //             'options'    => [
            //                 $familyOptions
            //             ],
            //             'fetchRoute' => [
            //                 'name'       => 'grp.json.shop.families',
            //                 'parameters' => [
            //                     'shop' => $masterProduct->shop->id
            //                 ]
            //             ],
            //             'valueProp'  => 'id',
            //             'labelProp'  => 'code',
            //             'required'   => true,
            //             'value'      => $masterProduct->family->id ?? null,
            //             'type_label' => 'families'
            //         ]
            //     ],
            // ],
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }

    public function getBreadcrumbs(MasterAsset $masterAsset, string $routeName, array $routeParameters): array
    {
        return ShowMasterProducts::make()->getBreadcrumbs(
            masterAsset: $masterAsset,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
