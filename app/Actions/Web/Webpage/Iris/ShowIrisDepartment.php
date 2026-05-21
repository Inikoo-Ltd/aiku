<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\IrisAction;
use App\Enums\UI\Catalogue\IrisDepartmentTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Catalogue\DepartmentResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisDepartment extends IrisAction
{
    public function handle(ProductCategory $department): ProductCategory
    {
        return $department;
    }

    public function asController(ProductCategory $department, ActionRequest $request): ProductCategory
    {
        $this->initialisation($request)->withTab(IrisDepartmentTabsEnum::values());

        return $this->handle($department);
    }

    public function htmlResponse(ProductCategory $department, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/Department',
            [
                'catalogue_scope' => 'department',
                'title'           => $department->name,
                'pageHead'        => [
                    'title'     => $department->name,
                    'model'     => __('Department'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Department'),
                    ],
                    'iconRight' => $department->state->stateIcon()[$department->state->value],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => IrisDepartmentTabsEnum::navigation($department),
                ],

                'mini_breadcrumbs' => array_filter([
                    [
                        'label'   => $department->name,
                        'to'      => [
                            'name'       => 'iris.catalogue.department.show',
                            'parameters' => [
                                'department' => $department->slug,
                            ],
                        ],
                        'tooltip' => __('Department'),
                        'icon'    => ['fal', 'folder-tree'],
                    ],
                ]),

                'data' => [
                    'department'    => DepartmentResource::make($department)->resolve(),
                    'data_feed_url' => route('iris.product_category.data_feed', ['productCategory' => $department->slug]),
                ],

                IrisDepartmentTabsEnum::SUB_DEPARTMENTS->value => $this->tab == IrisDepartmentTabsEnum::SUB_DEPARTMENTS->value
                    ?
                    fn () => SubDepartmentsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'sub_department',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::SUB_DEPARTMENTS->value
                        )
                    )
                    : Inertia::lazy(fn () => SubDepartmentsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'sub_department',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::SUB_DEPARTMENTS->value
                        )
                    )),

                IrisDepartmentTabsEnum::FAMILIES->value => $this->tab == IrisDepartmentTabsEnum::FAMILIES->value
                    ?
                    fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::FAMILIES->value
                        )
                    )
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::FAMILIES->value
                        )
                    )),

                IrisDepartmentTabsEnum::PRODUCTS->value => $this->tab == IrisDepartmentTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::PRODUCTS->value
                        )
                    )),

                IrisDepartmentTabsEnum::COLLECTIONS->value => $this->tab == IrisDepartmentTabsEnum::COLLECTIONS->value
                    ?
                    fn () => CollectionsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'collection',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )
                    : Inertia::lazy(fn () => CollectionsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'collection',
                                'parent'     => 'department',
                                'parent_key' => $department->id,
                            ],
                            $request,
                            IrisDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )),
            ]
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'sub_department',
                parent: 'department',
                prefix: IrisDepartmentTabsEnum::SUB_DEPARTMENTS->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'family',
                parent: 'department',
                prefix: IrisDepartmentTabsEnum::FAMILIES->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'product',
                parent: 'department',
                prefix: IrisDepartmentTabsEnum::PRODUCTS->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'collection',
                parent: 'department',
                prefix: IrisDepartmentTabsEnum::COLLECTIONS->value
            )
        );
    }
}
