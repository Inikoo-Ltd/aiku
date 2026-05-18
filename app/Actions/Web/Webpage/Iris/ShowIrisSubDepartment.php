<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\IrisAction;
use App\Enums\UI\Catalogue\IrisSubDepartmentTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisSubDepartment extends IrisAction
{
    public function handle(ProductCategory $subDepartment): ProductCategory
    {
        return $subDepartment;
    }

    public function asController(ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {
        $this->initialisation($request)->withTab(IrisSubDepartmentTabsEnum::values());

        return $this->handle($subDepartment);
    }

    public function htmlResponse(ProductCategory $subDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/SubDepartment',
            [
                'catalogue_scope' => 'sub_department',
                'title'           => $subDepartment->name,
                'pageHead'        => [
                    'title'     => $subDepartment->name,
                    'model'     => __('Sub Department'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-dot-circle'],
                        'title' => __('Sub Department'),
                    ],
                    'iconRight' => $subDepartment->state->stateIcon()[$subDepartment->state->value],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => IrisSubDepartmentTabsEnum::navigation($subDepartment),
                ],

                'data' => [
                    'sub_department' => SubDepartmentResource::make($subDepartment)->resolve(),
                    'data_feed_url'  => route('iris.product_category.data_feed', ['productCategory' => $subDepartment->slug]),
                ],

                IrisSubDepartmentTabsEnum::FAMILIES->value => $this->tab == IrisSubDepartmentTabsEnum::FAMILIES->value
                    ?
                    fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::FAMILIES->value
                        )
                    )
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'family',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::FAMILIES->value
                        )
                    )),

                IrisSubDepartmentTabsEnum::PRODUCTS->value => $this->tab == IrisSubDepartmentTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::PRODUCTS->value
                        )
                    )),

                IrisSubDepartmentTabsEnum::COLLECTIONS->value => $this->tab == IrisSubDepartmentTabsEnum::COLLECTIONS->value
                    ?
                    fn () => CollectionsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'collection',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )
                    : Inertia::lazy(fn () => CollectionsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'collection',
                                'parent'     => 'sub_department',
                                'parent_key' => $subDepartment->id,
                            ],
                            $request,
                            IrisSubDepartmentTabsEnum::COLLECTIONS->value
                        )
                    )),
            ]
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'family',
                parent: 'sub_department',
                prefix: IrisSubDepartmentTabsEnum::FAMILIES->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'product',
                parent: 'sub_department',
                prefix: IrisSubDepartmentTabsEnum::PRODUCTS->value
            )
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'collection',
                parent: 'sub_department',
                prefix: IrisSubDepartmentTabsEnum::COLLECTIONS->value
            )
        );
    }
}
