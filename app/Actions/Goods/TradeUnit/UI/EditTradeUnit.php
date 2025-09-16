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
                                    'label' => __('code'),
                                    'value' => $tradeUnit->code
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $tradeUnit->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $tradeUnit->description_title
                                ],
                                'description' => [
                                    'type'  => 'textarea',
                                    'label' => __('description'),
                                    'value' => $tradeUnit->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Extra description'),
                                    'value' => $tradeUnit->description_extra
                                ],
                                'gross_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('gross weight'),
                                    'value' => $tradeUnit->gross_weight,
                                    'bind'  =>[
                                        'suffix' => 'g'
                                    ]
                                ],
                                 'net_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('net weight'),
                                    'value' => $tradeUnit->net_weight,
                                    'bind'  =>[
                                        'suffix' => 'g'
                                    ]
                                ],
                                'marketing_weight' => [
                                    'type'  => 'input_number',
                                    'label' => __('marketing weight'),
                                    'value' => $tradeUnit->marketing_weight,
                                    'bind'  =>[
                                        'suffix' => 'g'
                                    ]
                                ],
                                'dimension' => [
                                    'type'  => 'input-dimension',
                                    'label' => __('dimension'),
                                    'value' => $tradeUnit->dimension,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('translate'),
                            'icon'   => 'fa-light fa-language',
                            'fields' => [
                                'name_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate name'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->name,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('name_i8n')
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate description title'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description_title,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_title_i8n')
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_i8n')
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description extra'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'main' => $tradeUnit->description_extra,
                                    'full' => true,
                                    'value' => $tradeUnit->getTranslations('description_extra_i8n')
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Tags & Brands'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'tags' => [
                                    'type'  => 'tags-trade-unit',
                                    'label' => __('Tags'),
                                    'value' => $tradeUnit->tags->pluck('id')->toArray(),
                                    'tag_routes' => $tagRoute
                                ],
                                 'brands' => [
                                    'type'  => 'brands-trade-unit',
                                    'label' => __('Brands'),
                                    'value' => $tradeUnit->brand()?->id,
                                    'brand_routes' =>  $brandRoute
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
