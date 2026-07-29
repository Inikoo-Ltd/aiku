<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\StockFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStockFamily extends OrgAction
{
    use WithBucketNavigation;

    use WithGoodsEditAuthorisation;

    public function handle(StockFamily $stockFamily): StockFamily
    {
        return $stockFamily;
    }

    public function asController(StockFamily $stockFamily, ActionRequest $request): StockFamily
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stockFamily);
    }

    public function htmlResponse(StockFamily $stockFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'                            => __('stock family'),
                'breadcrumbs'                      => $this->getBreadcrumbs($stockFamily),
                'navigation'                       => [
                'previous' => $this->getPrevious($stockFamily, $request),
                'next'     => $this->getNext($stockFamily, $request),
                ],
                'pageHead'    => [
                    'title'     => $stockFamily->name,
                    'icon'      => [
                        'title' => __("stock's families"),
                        'icon'  => 'fal fa-boxes-alt'
                    ],
                    'actions'   => [
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
                            'title'  => __('Edit stock family'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $stockFamily->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $stockFamily->name
                                ],
                            ]
                        ]

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.stock-family.update',
                            'parameters' => $stockFamily->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(StockFamily $stockFamily): array
    {
        return ShowStockFamily::make()->getBreadcrumbs(
            routeParameters: ['stockFamily' => $stockFamily->slug],
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(StockFamily $stockFamily, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getNeighbour($stockFamily, $request, forward: false), $request->route()->getName());
    }

    public function getNext(StockFamily $stockFamily, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getNeighbour($stockFamily, $request, forward: true), $request->route()->getName());
    }

    private function getNeighbour(StockFamily $stockFamily, ActionRequest $request, bool $forward): ?StockFamily
    {
        $query = StockFamily::query()->where('stock_families.group_id', $stockFamily->group_id);
        $state = match ($request->input('bucket')) {
            'active'        => StockFamilyStateEnum::ACTIVE,
            'discontinuing' => StockFamilyStateEnum::DISCONTINUING,
            'discontinued'  => StockFamilyStateEnum::DISCONTINUED,
            'in_process'    => StockFamilyStateEnum::IN_PROCESS,
            default         => null,
        };

        if ($state) {
            $query->where('stock_families.state', $state);
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $stockFamily,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code' => 'stock_families.code',
                'name' => 'stock_families.name',
            ],
            defaultSort: ['stock_families.code', false],
            forward: $forward
        );
    }

    private function getNavigation(?StockFamily $stockFamily, string $routeName): ?array
    {
        if (!$stockFamily) {
            return null;
        }

        return match ($routeName) {
            'grp.goods.stock-families.edit' => [
                'label' => $stockFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stockFamily' => $stockFamily->slug
                    ]

                ]
            ]
        };
    }
}
