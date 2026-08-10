<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCatalogueIndexSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDepartmentsSales extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCatalogueIndexSubNavigation;

    public const string PREFIX = 'sales';

    private Shop $parent;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        return IndexDepartments::make()->handle($shop, self::PREFIX);
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return DepartmentsResource::collection($departments);
    }

    public function htmlResponse(LengthAwarePaginator $departments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Departments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [],
                'title'       => __('Departments sales'),
                'pageHead'    => [
                    'title'         => __('Departments'),
                    'model'         => '',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Departments')
                    ],
                    'afterTitle'    => [
                        'label' => __('Sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $this->getDepartmentsIndexSubNavigation($this->parent),
                ],
                'data'        => DepartmentsResource::collection($departments),
                'tabs'        => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX  => DepartmentsResource::collection($departments),
            ]
        )->table(
            IndexDepartments::make()->tableStructure(parent: $this->parent, prefix: self::PREFIX, sales: true)
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return IndexDepartments::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            '('.__('Sales').')'
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
