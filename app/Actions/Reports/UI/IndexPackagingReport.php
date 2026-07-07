<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 22:41:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexPackagingReport extends OrgAction
{
    use AsAction;

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Reports/PackagingReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title' => __('Packaging Reports'),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-boxes'],
                        'title' => __('Packaging Reports')
                    ],
                    'title' => __('Packaging Reports'),
                ],
                'downloadRoute' => [
                    'name' => 'grp.org.reports.packaging.download',
                    'parameters' => $request->route()->originalParameters()
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'icon' => 'fal fa-boxes',
                        'label' => __('Packaging Reports'),
                        'route' => [
                            'name' => 'grp.org.reports.packaging',
                            'parameters' => $routeParameters
                        ]
                    ]
                ]
            ]
        );
    }
}
