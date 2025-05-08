<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-14h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\TopUp\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Enums\UI\Accounting\TopUpTabsEnum;
use App\Models\Accounting\TopUp;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTopUp extends OrgAction
{
    public function handle(TopUp $topUp): TopUp
    {
        return $topUp;
    }

    public function asController(Organisation $organisation, Shop $shop, TopUp $topUp, ActionRequest $request): TopUp
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($topUp);
    }

    public function htmlResponse(TopUp $topUp, ActionRequest $request): Response
    {
        $title = (string) ($topUp->reference ?? $topUp->id);
        return Inertia::render(
            'Org/Accounting/TopUp',
            [
                'title'                                 => $title,
                'breadcrumbs'                           => $this->getBreadcrumbs($topUp, $request->route()->getName(), $request->route()->originalParameters()),
                'pageHead'    => [
                    'model'     => __('top up'),
                    'icon'      => 'fal fa-shopping-basket',
                    'title'     => $title,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TopUpTabsEnum::navigation()
                ],

            ]
        );
    }

    public function getBreadcrumbs(TopUp $topUp, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (TopUp $topUp, array $routeParameters, string $suffix = null) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Top Ups'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $topUp->reference ?? __('No reference'),
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.dashboard.payments.accounting.top_ups.show' => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs(
                    'grp.org.accounting.dashboard',
                    $routeParameters
                ),
                $headCrumb(
                    $topUp,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.dashboard.payments.accounting.top_ups.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.dashboard.payments.accounting.top_ups.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}
