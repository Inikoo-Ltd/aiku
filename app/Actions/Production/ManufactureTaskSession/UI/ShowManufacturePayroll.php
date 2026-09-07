<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufacturePayroll extends OrgAction
{
    public function handle(Production $production): Production
    {
        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "human-resources.{$this->organisation->id}.view",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Production/ManufacturePayroll',
            [
                'title'       => __('Payroll'),
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'pageHead'    => [
                    'title' => __('Payroll'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-money-check-alt'],
                        'title' => __('Payroll'),
                    ],
                ],
                'payroll_export_route' => [
                    'name'       => 'grp.org.productions.show.artisans.payroll.export',
                    'parameters' => $routeParameters
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowArtisansDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.artisans.payroll',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Payroll'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
