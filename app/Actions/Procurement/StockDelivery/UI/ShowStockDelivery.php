<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\StockDelivery\UI;

use App\Actions\InertiaAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property StockDelivery $stockDelivery
 */
class ShowStockDelivery extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->maya) {
            return true;
        }
        $this->canEdit = $request->user()->hasPermissionTo('procurement.edit');

        return $request->user()->hasPermissionTo("procurement.view");
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->initialisation($request)->withTab(StockDeliveryTabsEnum::values());
        $this->stockDelivery    = $stockDelivery;
    }

    public function maya(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->maya   =true;
        $this->initialisation($request)->withTab(StockDeliveryTabsEnum::values());
        $this->stockDelivery = $stockDelivery;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $this->validateAttributes();


        return Inertia::render(
            'Procurement/StockDelivery',
            [
                'title'                                 => __('supplier delivery'),
                'breadcrumbs'                           => $this->getBreadcrumbs($this->stockDelivery),
                'navigation'                            => [
                    'previous' => $this->getPrevious($this->stockDelivery, $request),
                    'next'     => $this->getNext($this->stockDelivery, $request),
                ],
                'pageHead'    => [
                    'icon'  => ['fal', 'people-arrows'],
                    'title' => $this->stockDelivery->id,
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                ],
                'tabs'=> [
                    'current'    => $this->tab,
                    'navigation' => StockDeliveryTabsEnum::navigation()
                ],
            ]
        );
    }


    public function jsonResponse(): StockDeliveryResource
    {
        return new StockDeliveryResource($this->stockDelivery);
    }

    public function getBreadcrumbs(StockDelivery $stockDelivery, $suffix = null): array
    {
        return array_merge(
            (new ShowProcurementDashboard())->getBreadcrumbs(),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.org.procurement.stock_deliveries.index',
                            ],
                            'label' => __('supplier delivery')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.stock_deliveries.show',
                                'parameters' => [$stockDelivery->slug]
                            ],
                            'label' => $stockDelivery->reference,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(StockDelivery $stockDelivery, ActionRequest $request): ?array
    {
        $previous = StockDelivery::where('number', '<', $stockDelivery->number)->orderBy('number', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(StockDelivery $stockDelivery, ActionRequest $request): ?array
    {
        $next = StockDelivery::where('number', '>', $stockDelivery->number)->orderBy('number')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?StockDelivery $stockDelivery, string $routeName): ?array
    {
        if (!$stockDelivery) {
            return null;
        }
        return match ($routeName) {
            'grp.org.procurement.stock_deliveries.show'=> [
                'label'=> $stockDelivery->reference,
                'route'=> [
                    'name'      => $routeName,
                    'parameters'=> [
                        'employee'=> $stockDelivery->number
                    ]

                ]
            ]
        };
    }
}
