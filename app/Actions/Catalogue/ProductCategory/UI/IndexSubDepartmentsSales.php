<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCatalogueIndexSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexSubDepartmentsSales extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCatalogueIndexSubNavigation;

    public const string PREFIX = 'sales';

    private Shop $parent;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        return IndexSubDepartments::make()->handle($shop, self::PREFIX);
    }

    public function jsonResponse(LengthAwarePaginator $subDepartments): AnonymousResourceCollection
    {
        return SubDepartmentsResource::collection($subDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $subDepartments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/SubDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [],
                'title'       => __('Sub-departments sales'),
                'pageHead'    => [
                    'title'         => __('Sub-departments'),
                    'model'         => '',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder-download'],
                        'title' => __('Sub-departments')
                    ],
                    'afterTitle'    => [
                        'label' => __('Sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $this->getSubDepartmentsIndexSubNavigation($this->parent),
                ],
                'data'        => SubDepartmentsResource::collection($subDepartments),
                'tabs'        => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX  => SubDepartmentsResource::collection($subDepartments),
            ]
        )->table(
            IndexSubDepartments::make()->tableStructure(parent: $this->parent, prefix: self::PREFIX, sales: true)
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return IndexSubDepartments::make()->getBreadcrumbs(
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
