<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:26:52 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Models\Goods\TradeUnit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

class EditTradeUnit extends OrgAction
{
    use WithGoodsAuthorisation;


    public function handle(TradeUnit $tradeUnit): TradeUnit
    {
        return $tradeUnit;
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($tradeUnit);
    }

    public function htmlResponse(TradeUnit $tradeUnit, ActionRequest $request): Response
    {

        $tagRoute = [
           'index_tag' => [
               'name'       => 'grp.json.trade_units.tags.index',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ]
           ],
           'store_tag' => [
               'name'       => 'grp.models.trade-unit.tags.store',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ]
           ],
           'update_tag' => [
               'name'       => 'grp.models.trade-unit.tags.update',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ],
               'method'    => 'patch'
           ],
           'delete_tag' => [
               'name'       => 'grp.models.trade-unit.tags.delete',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ],
               'method'    => 'delete'
           ],
           'attach_tag' => [
               'name'       => 'grp.models.trade-unit.tags.attach',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ],
               'method'    => 'post'
           ],
           'detach_tag' => [
               'name'       => 'grp.models.trade-unit.tags.detach',
               'parameters' => [
                   'tradeUnit' => $tradeUnit->id,
               ],
               'method'    => 'delete'
           ],
        ];

        $brandRoute = [
          'index_brand' => [
              'name'       => 'grp.json.brands.index',
              'parameters' => []
          ],
          'store_brand' => [
              'name'       => 'grp.models.trade-unit.brands.store',
              'parameters' => [
                  'tradeUnit' => $tradeUnit->id,
              ]
          ],
          'update_brand' => [
              'name'       => 'grp.models.trade-unit.brands.update',
              'parameters' => [
                  'tradeUnit' => $tradeUnit->id,
              ],
              'method'    => 'patch'
          ],
          'delete_brand' => [
              'name'       => 'grp.models.trade-unit.brands.delete',
              'parameters' => [
                  'tradeUnit' => $tradeUnit->id,
              ],
              'method'    => 'delete'
          ],
          'attach_brand' => [
              'name'       => 'grp.models.trade-unit.brands.attach',
              'parameters' => [
                  'tradeUnit' => $tradeUnit->id,
              ],
              'method'    => 'post'
          ],
          'detach_brand' => [
              'name'       => 'grp.models.trade-unit.brands.detach',
              'parameters' => [
                  'tradeUnit' => $tradeUnit->id,
                  'brand' => $tradeUnit->brand()?->id,
              ],
              'method'    => 'delete'
          ],
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Trade Unit'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $tradeUnit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($tradeUnit, $request),
                    'next'     => $this->getNext($tradeUnit, $request),
                ],
                'pageHead' => [
                    'title'    => $tradeUnit->name,
                    'icon'     => [
                        'title' => __('Trade Unit'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'actions'  => [
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
                    'blueprint' => [
                        [
                            'label'  => __('Id'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $tradeUnit->code
                                ],
                                'cpnp_number' => [
                                    'type' => 'input',
                                    'label' => __('CPNP Number'),
                                    'value' => $tradeUnit->cpnp_number
                                ],
                                'scpn_number' => [
                                    'type' => 'input',
                                    'label' => __('SCPN number'),
                                    'value' => $tradeUnit->scpn_number
                                ],
                                'ufi_number' => [
                                    'type' => 'input',
                                    'label' => __('UFI Number'),
                                    'value' => $tradeUnit->ufi_number
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $tradeUnit->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('Description title'),
                                    'value' => $tradeUnit->description_title
                                ],
                                'description' => [
                                    'type'  => 'textarea',
                                    'label' => __('Description'),
                                    'value' => $tradeUnit->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Extra description'),
                                    'value' => $tradeUnit->description_extra
                                ],
                                 'type' => [
                                    'type'  => 'input',
                                    'label' => __('Unit label'),
                                    'value' => $tradeUnit->type
                                ],
                                'gross_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('Gross weight'),
                                    'value' => $tradeUnit->gross_weight,
                                    'bind'  => [
                                        'suffix' => 'g'
                                    ]
                                ],
                                 'net_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('Net weight'),
                                    'value' => $tradeUnit->net_weight,
                                    'bind'  => [
                                        'suffix' => 'g'
                                    ]
                                ],
                                'marketing_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('Marketing weight'),
                                    'value' => $tradeUnit->marketing_weight,
                                    'bind'  => [
                                        'suffix' => 'g'
                                    ]
                                ],
                                'marketing_dimensions' => [
                                    'type'  => 'input-dimension',
                                    'label' => __('Marketing dimension'),
                                    'value' => $tradeUnit->marketing_dimensions,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('translate'),
                            'icon'   => 'fa-light fa-language',
                            'fields' => [
                                'name_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('Translate name'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->name,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('name_i8n')
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('Translate description title'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description_title,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_title_i8n')
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('Translate description'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_i8n')
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('Translate description extra'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description_extra,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_extra_i8n')
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Tags & Brands'),
                            'icon'   => 'fa-light fa-tags',
                            'fields' => [
                                'tags' => [
                                    'type'  => 'tags-trade-unit',
                                    'label' => __('Tags'),
                                    'value' => $tradeUnit->tags->pluck('id')->toArray(),
                                    'tag_routes' => $tagRoute,
                                    'isWithRefreshFieldform'    => true
                                ],
                                 'brands' => [
                                    'type'  => 'brands-trade-unit',
                                    'label' => __('Brands'),
                                    'value' => $tradeUnit->brand()?->id,
                                    'brand_routes' =>  $brandRoute
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Family'),
                            'icon'   => 'fa-light fa-folder-tree',
                            'fields' => [
                                'trade_unit_family_id'  =>  [
                                    'type'    => 'select_infinite',
                                    'label'   => __('Family'),
                                    'options'   => [
                                            [
                                                'id' =>  $tradeUnit->tradeUnitFamily->id ?? null,
                                                'code' =>  $tradeUnit->tradeUnitFamily->code ?? null,
                                                'name' => $tradeUnit->tradeUnitFamily->name ?? null,
                                                'number_trade_units' => $tradeUnit->tradeUnitFamily->stats->number_trade_units ?? 0
                                            ]
                                    ],
                                    'fetchRoute'    => [
                                        'name'       => 'grp.masters.trade-unit-families.index',
                                        'parameters' => [
                                        ]
                                    ],
                                    'required' => true,
                                    'valueProp' => 'id',
                                    'type_label' => 'department-and-sub-department',
                                    'labelProp' => 'code',
                                    'value'   => $tradeUnit->tradeUnitFamily->id ?? null,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-book-open',
                            'fields' => [
                                'ingredients' => [
                                    'type'  => 'select_infinite',
                                    'label' => __('Ingredients'),
                                    'value' => $tradeUnit->ingredients,
                                    'mode' => 'tags',
                                    'fetchRoute' => [
                                        'name'       => 'grp.goods.ingredients.index',
                                        'parameters' => []
                                    ],
                                    'valueProp'  => 'slug',
                                    'labelProp'  => 'name',

                                ],
                                'origin_country_id' => [
                                    'type'  => 'select',
                                    'label' => __('Country of Origin'),
                                    'value' => $tradeUnit->origin_country_id,
                                    'options' => GetCountriesOptions::run(),
                                    'valueProp' => 'id',
                                ],
                                'tariff_code' => [
                                    'type'  => 'input',
                                    'label' => __('Tariff Code'),
                                    'value' => $tradeUnit->tariff_code
                                ],
                                'duty_rate' => [
                                    'type'  => 'input',
                                    'label' => __('Duty Rate'),
                                    'value' => $tradeUnit->duty_rate
                                ],
                                'hts_us' => [
                                    'type'  => 'input',
                                    'label' => __('HTS US'),
                                    'value' => $tradeUnit->hts_us,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Health & Safety'),
                            'icon'   => 'fa-light fa-notes-medical',
                            'fields' => [
                                'un_number' => [
                                    'type'  => 'input',
                                    'label' => __('UN Number'),
                                    'value' => $tradeUnit->un_number
                                ],
                                'un_class' => [
                                    'type'  => 'input',
                                    'label' => __('UN Class'),
                                    'value' => $tradeUnit->un_class
                                ],
                                'packing_group' => [
                                    'type'  => 'input',
                                    'label' => __('Packing Group'),
                                    'value' => $tradeUnit->packing_group
                                ],
                                'proper_shipping_name' => [
                                    'type'  => 'input',
                                    'label' => __('Proper Shipping Name'),
                                    'value' => $tradeUnit->proper_shipping_name
                                ],
                                'hazard_identification_number' => [
                                    'type'  => 'input',
                                    'label' => __('Hazard Identification Number'),
                                    'value' => $tradeUnit->hazard_identification_number,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('GPSR (if empty will use Part GPSR)'),
                            'icon'   => 'fa-light fa-biohazard',
                            'fields' => [
                                'gpsr_manufacturer' => [
                                    'type'  => 'input',
                                    'label' => __('Manufacturer'),
                                    'value' => $tradeUnit->gpsr_manufacturer
                                ],
                                'gpsr_eu_responsible' => [
                                    'type'  => 'input',
                                    'label' => __('EU Responsible'),
                                    'value' => $tradeUnit->gpsr_eu_responsible
                                ],
                                'gpsr_warnings' => [
                                    'type'  => 'input',
                                    'label' => __('Warnings'),
                                    'value' => $tradeUnit->gpsr_warnings
                                ],
                                'gpsr_manual' => [
                                    'type'  => 'input',
                                    'label' => __('How To Use'),
                                    'value' => $tradeUnit->gpsr_manual
                                ],
                                'gpsr_class_category_danger' => [
                                    'type'  => 'input',
                                    'label' => __('Class & category of danger'),
                                    'value' => $tradeUnit->gpsr_class_category_danger,
                                ],
                                'pictogram_toxic' => [
                                    'type'  => 'toggle',
                                    'label' => __('Acute Toxicity'),
                                    'value' => $tradeUnit->pictogram_toxic,
                                    'suffixImage' => '/hazardIcon/toxic-icon.png'
                                ],
                                'pictogram_corrosive' => [
                                    'type'  => 'toggle',
                                    'label' => __('Corrosive'),
                                    'value' => $tradeUnit->pictogram_corrosive,
                                    'suffixImage' => '/hazardIcon/corrosive-icon.png'
                                ],
                                'pictogram_explosive' => [
                                    'type'  => 'toggle',
                                    'label' => __('Explosive'),
                                    'value' => $tradeUnit->pictogram_explosive,
                                    'suffixImage' => '/hazardIcon/explosive.jpg'
                                ],
                                'pictogram_flammable' => [
                                    'type'  => 'toggle',
                                    'label' => __('Flammable'),
                                    'value' => $tradeUnit->pictogram_flammable,
                                    'suffixImage' => '/hazardIcon/flammable.png'
                                ],
                                'pictogram_gas' => [
                                    'type'  => 'toggle',
                                    'label' => __('Gas Under Pressure'),
                                    'value' => $tradeUnit->pictogram_gas,
                                    'suffixImage' => '/hazardIcon/gas.png'
                                ],
                                'pictogram_environment' => [
                                    'type'  => 'toggle',
                                    'label' => __('Hazardous to the Environment'),
                                    'value' => $tradeUnit->pictogram_environment,
                                    'suffixImage' => '/hazardIcon/hazard-env.png'
                                ],
                                'pictogram_health' => [
                                    'type'  => 'toggle',
                                    'label' => __('Health Hazard'),
                                    'value' => $tradeUnit->pictogram_health,
                                    'suffixImage' => '/hazardIcon/health-hazard.png'
                                ],
                                'pictogram_oxidising' => [
                                    'type'  => 'toggle',
                                    'label' => __('Oxidising'),
                                    'value' => $tradeUnit->pictogram_oxidising,
                                    'suffixImage' => '/hazardIcon/oxidising.png'
                                ],
                                'pictogram_danger' => [
                                    'type'  => 'toggle',
                                    'label' => __('Serious Health Hazard'),
                                    'value' => $tradeUnit->pictogram_danger,
                                    'suffixImage' => '/hazardIcon/serious-health-hazard.png'
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Sale Status'),
                            'icon'   => 'fal fa-cart-arrow-down',
                            'fields' => [
                                'is_for_sale' => [
                                    'confirmation' => [
                                        'description' => __('Changing the sale status of a Trade Unit will affect all products linked to it in all shops.'),
                                    ],
                                    'type'  => 'toggle',
                                    'label' => __('For Sale'),
                                    'value' => $tradeUnit->is_for_sale,
                                ],
                            ],
                        ],
                    ],


                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.trade-unit.update',
                            'parameters' => $tradeUnit->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(TradeUnit $tradeUnit, string $routeName, array $routeParameters): array
    {
        return ShowTradeUnit::make()->getBreadcrumbs(
            tradeUnit: $tradeUnit,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $previous = TradeUnit::where('code', '<', $tradeUnit->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $next = TradeUnit::where('code', '>', $tradeUnit->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?TradeUnit $tradeUnit, string $routeName): ?array
    {
        if (!$tradeUnit) {
            return null;
        }


        return [
            'label' => $tradeUnit->name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'tradeUnit' => $tradeUnit->slug
                ]
            ]
        ];
    }
}
