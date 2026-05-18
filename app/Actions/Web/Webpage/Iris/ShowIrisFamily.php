<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\IrisAction;
use App\Enums\UI\Catalogue\IrisFamilyTabsEnum;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisFamily extends IrisAction
{
    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }

    public function asController(ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisation($request)->withTab(IrisFamilyTabsEnum::values());

        return $this->handle($family);
    }

    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/Family',
            [
                'catalogue_scope' => 'family',
                'title'           => $family->name,
                'pageHead'        => [
                    'title'     => $family->name,
                    'model'     => __('Family'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('Family'),
                    ],
                    'iconRight' => $family->state->stateIcon()[$family->state->value],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => IrisFamilyTabsEnum::navigation($family),
                ],

                'data' => [
                    'family'        => FamilyResource::make($family)->resolve(),
                    'data_feed_url' => route('iris.product_category.data_feed', ['productCategory' => $family->slug]),
                ],

                IrisFamilyTabsEnum::PRODUCTS->value => $this->tab == IrisFamilyTabsEnum::PRODUCTS->value
                    ?
                    fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'family',
                                'parent_key' => $family->id,
                            ],
                            $request,
                            IrisFamilyTabsEnum::PRODUCTS->value
                        )
                    )
                    : Inertia::lazy(fn () => ProductsResource::collection(
                        IndexIrisCatalogue::make()->action(
                            [
                                'scope'      => 'product',
                                'parent'     => 'family',
                                'parent_key' => $family->id,
                            ],
                            $request,
                            IrisFamilyTabsEnum::PRODUCTS->value
                        )
                    )),
            ]
        )->table(
            IndexIrisCatalogue::make()->tableStructure(
                scope: 'product',
                parent: 'family',
                prefix: IrisFamilyTabsEnum::PRODUCTS->value
            )
        );
    }
}
