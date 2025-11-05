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
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit, $request);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }

    public function handle(Organisation|TradeUnit $parent, ActionRequest $request): Response
    {
        $route = [
            'name'       => 'grp.org.tags.store',
            'parameters' => [
                'organisation' => $parent->slug
            ]
        ];

        if ($parent instanceof TradeUnit) {
            $route = [
                'name'       => 'grp.models.trade-units.tags.store',
                'parameters' => [
                    $parent->slug,
                ],
            ];
        }

        $scopes = collect(TagScopeEnum::cases())->map(fn ($case) => [
            'label' => $case->pretty(),
            'value' => $case->value,
        ])->toArray();

        return Inertia::render(
            'CreateModel',
            [
                'title'    => __('Create Tag'),
                'icon'     =>
                    [
                        'icon'  => ['fal', 'fa-tags'],
                        'title' => __('Tag'),
                    ],
                'pageHead' => [
                    'title'        => __('Create Tag'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create Tag'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                ],
                                'scope' => [
                                    'type'     => 'select',
                                    'label'    => __('Scope'),
                                    'required' => true,
                                    'options'  => $scopes,
                                ],
                            ],
                        ],
                    ],
                    'route' => $route,
                ],
            ],
        );
    }
}
