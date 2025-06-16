<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDepartmentsInOrganisation extends OrgAction
{
    use WithCollectionSubNavigation;
    use WithOrganisationOverviewAuthorisation;
    use WithDepartmentsInOverview;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    public function htmlResponse(LengthAwarePaginator $departments, ActionRequest $request): Response
    {
        $title      = __('Departments');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder-tree'],
            'title' => __('departments')
        ];
        $afterTitle = [
            'label' => '@ '.__('organisation').' '.$this->organisation->code,
        ];
        $iconRight  = null;


        return Inertia::render(
            'Overview/Catalogue/DepartmentsInOverview',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Departments'),
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                    'model'      => $model,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,

                ],
                'data'        => DepartmentsResource::collection($departments),


            ]
        )->table($this->tableStructure(parent: $this->organisation));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }


}
