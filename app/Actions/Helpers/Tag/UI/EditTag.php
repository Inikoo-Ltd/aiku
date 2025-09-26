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
use App\Models\Helpers\Tag;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTag extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Tag $tag): Tag
    {
        return $tag;
    }

    public function asController(Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tag->group, $request);

        return $this->handle($tag);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tag->group, $request);

        return $this->handle($tag);
    }

    public function htmlResponse(Tag $tag, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('tag'),
                'pageHead'    => [
                    'title'   => $tag->name,
                    'icon'    => [
                        'title' => __('Skus'),
                        'icon'  => 'fal fa-box'
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
                    'blueprint' => [
                        [
                            'title'  => __('Edit sku'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $tag->name
                                ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.tag.update',
                            'parameters' => $tag->slug

                        ],
                    ]
                ]
            ]
        );
    }
}
