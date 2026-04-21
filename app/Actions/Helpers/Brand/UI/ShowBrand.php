<?php

namespace App\Actions\Helpers\Brand\UI;

use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Actions\Helpers\Brand\WithBrandSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\BrandTabsEnum;
use App\Http\Resources\Catalogue\BrandResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Helpers\Brand;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class ShowBrand extends GrpAction
{
    use WithGoodsAuthorisation;
    use WithBrandSubNavigation;

    public function htmlResponse(Brand $brand, ActionRequest $request)
    {
        return Inertia::render('Goods/Brand', 
            [
                'title'       => __('Brand').' '.$brand->slug,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $brand,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                // 'navigation'  => [
                //     'previous' => $this->getPrevious($tradeUnit, $request),
                //     'next'     => $this->getNext($tradeUnit, $request),
                // ],
                'pageHead'    => [
                    'icon'       => [
                        'title' => __('Brand'),
                        'icon'  => 'fal fa-copyright'
                    ],
                    'model'      => __('Brand'),
                    'title'      => $brand->name,
                    'actions'    => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $this->getBrandSubNavigation($brand)
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => BrandTabsEnum::navigation()
                ],

                BrandTabsEnum::SHOWCASE->value => $this->tab == BrandTabsEnum::SHOWCASE->value ? 
                    fn () => BrandResource::make($brand) 
                    : Inertia::lazy(fn () => BrandResource::make($brand)),

                BrandTabsEnum::HISTORY->value => $this->tab == BrandTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($brand))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($brand))),
            ]
        )
        ->table(IndexHistory::make()->tableStructure(BrandTabsEnum::HISTORY->value));
    }

    public function asController(Brand $brand, ActionRequest $request)
    {
        $this->initialisation($brand->group, $request)->withTab(BrandTabsEnum::values());

        return $brand;
    }

    
    public function getBreadcrumbs(Brand $brand, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Brand $brand, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Brands')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $brand->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.trade_units.brands.edit',
            'grp.trade_units.brands.show' =>
            array_merge(
                ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $brand,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
