<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:43:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Reports;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexReports extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Organisation|Shop $scope): Shop|Organisation
    {
        return $scope;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inShop(Shop $shop): Shop
    {
        return $this->handle($shop);
    }

    public function htmlResponse(Organisation|Shop $scope, ActionRequest $request): Response
    {
        $container = null;
        if (class_basename($scope) == 'Shop') {
            $container = [
                'icon' => ['fal', 'fa-store-alt'],
                'tooltip' => Str::possessive($scope->name).' '.__('Reports'),
                'label' => $scope->code,
            ];
        }

        return Inertia::render(
            'Org/Reports/Reports',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => 'BI',
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-chart-line'],
                        'title' => __('Reports'),
                    ],
                    'title' => __('reports'),
                    'container' => $container,
                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            default => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.reports.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Reports'),
                        ],
                    ],
                ]
            )
        };
    }
}
