<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class EditMasterProduct extends OrgAction
{
    use WithMasterProductNavigation;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function inGroup(MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterAsset $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterShop->group, $request);

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
                'title'       => __('Editing master product').': '.$masterAsset->code,
                'warning'     => $masterAsset->stats->number_assets > 0 ? [
                    'type'  => 'warning',
                    'title' => __('Important'),
                    'text'  => __('Changes to this master name or descriptions will overwrite child product names and descriptions where “Follow Master” is enabled.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterAsset,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterAsset, $request),
                    'next'     => $this->getNextModel($masterAsset, $request),
                ],
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
        $packedIn = $masterProduct->getStockPackedInByTradeUnit();

        $tradeUnits = $masterProduct->tradeUnits->map(function (TradeUnit $tradeUnit) use ($packedIn) {
            /** @var MorphPivot $pivot */
            $pivot            = $tradeUnit->getRelationValue('pivot');
            $quantity         = $pivot->getAttribute('quantity');
            $packedInQuantity = Arr::get($packedIn, $tradeUnit->id, 1);
            $fraction         = $quantity / $packedInQuantity;

            return array_merge(
                [
                    'quantity'        => (int)$quantity,
                    'packed_in'       => $packedInQuantity,
                    'fraction'        => $fraction,
                    'pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($fraction)), $packedInQuantity),
                ],
                $tradeUnit->toArray()
            );
        });

        $currenciesRate = GetMasterShopCurrenciesRate::run($masterProduct->masterShop);

        return [
            [
                'label'  => __('Id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'code' => [
                        'type'  => 'input',
                        'label' => __('Code'),
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
                    'description'       => [
                        'type'    => 'textEditor',
                        'label'   => __('Description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description,
                        'toggle'  => [
                            'heading2',
                            'heading3',
                            'fontSize',
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            "fontFamily",
                            'orderedList',
                            'blockquote',
                            'divider',
                            'alignLeft',
                            'alignRight',
                            "link",
                            'alignCenter',
                            'undo',
                            'redo',
                            'highlight',
                            'color',
                            'clear'
                        ],
                    ],
                    'description_extra' => [
                        'type'    => 'textEditor',
                        'label'   => __('Extra description'),
                        'options' => [
                            'counter' => true,
                        ],
                        'value'   => $masterProduct->description_extra,
                        'toggle'  => [
                            'heading2',
                            'heading3',
                            'fontSize',
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            "fontFamily",
                            'orderedList',
                            'blockquote',
                            'divider',
                            'alignLeft',
                            'alignRight',
                            "link",
                            'alignCenter',
                            'undo',
                            'redo',
                            'highlight',
                            'color',
                            'clear'
                        ],
                    ],
                ]
            ],
            [
                'label'  => __('Pricing'),
                'icon'   => 'fa-light fa-money-bill',
                'fields' => [
                    'master_prices'            => [
                        'type'          => 'multiple_price_currency',
                        'label'         => __('Price').' / '.__('Outer'),
                        'required'      => true,
                        'currencies'    => $currenciesRate,
                        'value'         => $masterProduct->master_prices,
                        'masterAsset'   => $masterProduct->id,
                        'type_input'          => 'price'
                    ],
                    'master_rrps'            => [
                        'type'          => 'multiple_price_currency',
                        'label'         => __('RRP').' / '.__('Unit'),
                        'required'      => true,
                        'currencies'    => $currenciesRate,
                        'value'         => $masterProduct->master_rrps,
                        'masterAsset'   => $masterProduct->id,
                        'type_input'          => 'rrp'
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
                        'label' => __('Unit label'),
                        'value' => $masterProduct->unit,
                    ],

                ]
            ],
            [
                'label'  => __('Master family'),
                'icon'   => 'fal fa-folder',
                'fields' => [
                    'master_family_id' => [
                        'type'       => 'select_infinite',
                        'label'      => __('Master family'),
                        'options'    => [
                            $masterProduct->masterFamily ? MasterFamiliesResource::make($masterProduct->masterFamily)->toArray(request()) : []
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
                        'label'        => __('Trade units'),
                        'type'         => 'list-selector-trade-unit',
                        'key_quantity' => 'quantity',
                        'withQuantity' => true,
                        'full'         => true,
                        'noSaveButton' => true,
                        'use_confirm'  => true,
                        'is_dropship'  => $masterProduct->masterShop->type == ShopTypeEnum::DROPSHIPPING,
                        'tabs' => array_values(array_filter([
                            $masterProduct->masterFamily ? [
                                'label'      => __('To do'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.recommended-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->id,
                                    ],
                                ],
                            ] : null,

                            $masterProduct->masterFamily ? [
                                'label'      => __('Done'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.taken-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $masterProduct->masterFamily->id,
                                    ],
                                ],
                            ] : null,

                            [
                                'label'      => __('All'),
                                'search'     => true,
                                'routeFetch' => [
                                    'name' => 'grp.json.master_product_category.all_trade_units',
                                ],
                            ],
                        ])),
                        'value'        => $tradeUnits,
                    ],
                ],
            ],

            $masterProduct->not_for_sale_from_trade_unit
                ? []
                : [
                'label'  => __('Sale status'),
                'icon'   => 'fal fa-cart-arrow-down',
                'fields' => [
                    'is_for_sale' => [
                        'saveConfirmation' => [
                            'description' => __('Changing the sale status of a master product will affect all products linked to it in all shops.'),
                        ],
                        'type'         => 'toggle',
                        'label'        => __('For Sale'),
                        'value'        => $masterProduct->is_for_sale,
                    ],
                ],
            ],
            !$masterProduct->is_single_trade_unit
                ? []
                : [
                'label'  => __('Follow images'),
                'icon'   => 'fal fa-image',
                'fields' => [
                    'follow_trade_unit_media' => [
                        'type'         => 'toggle',
                        'label'        => __('Follow images from trade unit'),
                        'value'        => $masterProduct->follow_trade_unit_media,
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
