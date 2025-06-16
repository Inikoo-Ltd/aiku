<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Http\Resources\Catalogue\DepartmentsResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDepartmentsInGroup extends OrgAction
{
    use WithCollectionSubNavigation;
    use WithGroupOverviewAuthorisation;
    use WithDepartmentsInOverview;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
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
            'label' => '@ '.__('group').' '.$this->group->code,
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
        )->table($this->tableStructure(parent: $this->group));
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
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
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
