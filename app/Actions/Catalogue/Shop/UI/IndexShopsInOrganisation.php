<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:33 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Http\Resources\Api\Dropshipping\ShopsResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexShopsInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;
    use WithShopsInOverview;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    public function htmlResponse(LengthAwarePaginator $shops, ActionRequest $request): Response
    {
        $afterTitle = [
            'label' => '@ '.__('organisation').' '.$this->organisation->code,
        ];

        return Inertia::render(
            'Overview/Catalogue/ShopsInOverview',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('shops'),
                'pageHead'    => [
                    'title'   => __('shops'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-store-alt'],
                        'title' => __('shop'),
                        'afterTitle' => $afterTitle,

                    ],

                ],
                'data'        => ShopsResource::collection($shops),

            ]
        )->table($this->tableStructure(parent: $this->group, prefix: null));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Shops'),
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
