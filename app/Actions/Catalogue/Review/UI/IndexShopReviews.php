<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use App\Models\SysAdmin\Organisation;

class IndexShopReviews extends OrgAction
{
    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        return IndexReviews::run($shop, $prefix);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, 'reviews');
    }

    public function htmlResponse(?LengthAwarePaginator $reviews = null, ?ActionRequest $request = null)
    {
        $request ??= app(ActionRequest::class);
        $shop = $this->shop;

        if (!$shop) {
            abort(404);
        }

        $reviews ??= $this->handle($shop, 'reviews');

        return Inertia::render('Org/Catalogue/ShopReviews', [
            'title' => __('Reviews'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead' => [
                'title' => __('Reviews'),
                'model' => __('Shop'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-star'],
                    'title' => __('Reviews'),
                ],
            ],
            'shop_id' => $shop->id,
            'reviews' => ReviewsResource::collectionWithTabMeta($reviews, $shop),
        ])->table(IndexReviews::make()->tableStructure(parent: $shop, prefix: 'reviews'));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, mixed $suffix = null): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Reviews'),
                        'icon'  => 'fal fa-star',
                    ],
                    'suffix' => $suffix,
                ],
            ],
        );
    }
}
