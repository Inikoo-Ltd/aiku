<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:04 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Queries\UI;

use App\Actions\Helpers\Query\GetQueryEloquentQueryBuilder;
use App\Actions\InertiaAction;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\CRM\Prospect\ShowProspectTabsEnum;
use App\Http\Resources\CRM\ProspectsResource;
use App\Models\Helpers\Query;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProspectQuery extends InertiaAction
{
    use WithActionButtons;
    use WithProspectsSubNavigation;

    public Organisation|Shop $parent;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('crm.view');
    }

    public function handle(Query $query): Query
    {
        return $query;
    }

    public function asController(Shop $shop, Query $query, ActionRequest $request): Query
    {
        $this->parent = $shop;
        $this->initialisation($request)->withTab(ShowProspectTabsEnum::values());

        return $this->handle($query);
    }

    public function htmlResponse(Query $query, ActionRequest $request): Response
    {
        $subNavigation = $this->getSubNavigation($request);
        return Inertia::render(
            'Prospects/ProspectQuery',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => $query->name,
                'pageHead' => [
                    'title'            => $query->name,
                    'subNavigation'    => $subNavigation,
                    'actions'          => [
                        $this->getEditActionIcon($request)
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ShowProspectTabsEnum::navigation()
                ],


                ShowProspectTabsEnum::PROSPECTS->value => $this->tab == ShowProspectTabsEnum::PROSPECTS->value ?
                    fn () => ProspectsResource::collection(GetQueryEloquentQueryBuilder::run($query)->paginate())
                    : Inertia::lazy(fn () => ProspectsResource::collection(GetQueryEloquentQueryBuilder::run($query)->paginate())),
            ]
        )->table(IndexProspects::make()->tableStructure(parent: $this->parent, prefix: ShowProspectTabsEnum::PROSPECTS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Prospect list'),
                        'icon'  => 'fal fa-envelope'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.lists.show' =>
            array_merge(
                IndexProspectQueries::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.prospects.lists.show',
                        'parameters' => array_values($this->originalParameters)
                    ]
                ),
            ),

            default => []
        };
    }
}
