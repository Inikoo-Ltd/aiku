<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:33 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Http\Resources\Api\Dropshipping\ShopsResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexShopsInGroup extends OrgAction
{
    use WithGroupOverviewAuthorisation;
    use WithShopsInOverview;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }


    public function htmlResponse(LengthAwarePaginator $shops, ActionRequest $request): Response
    {
        $afterTitle = [
            'label' => '@ '.__('group').' '.$this->group->code,
        ];

        return Inertia::render(
            'Overview/Catalogue/ShopsInOverview',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('shops'),
                'pageHead'    => [
                    'title' => __('shops'),
                    'icon'  => [
                        'icon'       => ['fal', 'fa-store-alt'],
                        'title'      => __('shop'),
                        'afterTitle' => $afterTitle,

                    ]
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
