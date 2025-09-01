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
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $tradeUnit->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $tradeUnit->name
                                ],
                                'name_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate name'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($tradeUnit->group->extra_languages),
                                    'value' => $tradeUnit->getTranslations('name_i8n')
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $tradeUnit->description_title
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate description title'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'value' => $tradeUnit->getTranslations('description_title_i8n')
                                ],
                                'description' => [
                                    'type'  => 'textarea',
                                    'label' => __('description'),
                                    'value' => $tradeUnit->description
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'value' => $tradeUnit->getTranslations('description_i8n')
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description extra'),
                                    'value' => $tradeUnit->description_extra
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description extra'),
                                    'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($tradeUnit->group->extra_languages),
                                    'value' => $tradeUnit->getTranslations('description_extra_i8n')
                                ],
                                'gross_weight' => [
                                    'type'  => 'input',
                                    'label' => __('gross weight'),
                                    'value' => $tradeUnit->gross_weight
                                ],
                            ],
                        ]
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
