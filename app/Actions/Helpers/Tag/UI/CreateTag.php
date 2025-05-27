<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\TradeUnit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTag extends OrgAction
{
    use WithGoodsEditAuthorisation;

    protected TradeUnit $parent;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): Response
    {
        $this->parent = $tradeUnit;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit, $request);
    }

    public function handle(TradeUnit $parent, ActionRequest $request): Response
    {
        $route = [];

        if ($parent instanceof TradeUnit) {
            $route = [
                'name'       => 'grp.models.trade-units.tags.store',
                'parameters' => [
                    $parent->slug,
                ]
            ];
        }
        return Inertia::render(
            'CreateModel',
            [
                'title'    => __('new tag'),
                'icon'     =>
                    [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Tag')
                    ],
                'pageHead' => [
                    'title'        => __('new Tag'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('new Tag'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                            ]
                        ]
                    ],
                    'route' => $route,
                ],

            ]
        );
    }
}
