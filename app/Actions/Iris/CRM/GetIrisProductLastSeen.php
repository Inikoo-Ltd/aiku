<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductLastSeen extends IrisAction
{
    public const int MAX_PRODUCTS = 12;

    private Webpage $webpage;

    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId): Collection
    {
        if (!$customer && !$cookieId) {
            return collect();
        }

        $queryBuilder = QueryBuilder::for(Product::class)
            ->join('product_last_seens', 'product_last_seens.webpage_id', '=', 'products.webpage_id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->where('product_last_seens.shop_id', $webpage->shop_id)
            ->where('product_last_seens.webpage_id', '!=', $webpage->id);

        if ($customer) {
            $queryBuilder->where('product_last_seens.customer_id', $customer->id);
        } else {
            $queryBuilder->whereNull('product_last_seens.customer_id')
                ->where('product_last_seens.cookie_id', $cookieId);
        }

        $queryBuilder->select([
            'products.id',
            'products.code',
            'products.name',
            'products.available_quantity',
            'products.price',
            'products.rrp',
            'products.web_images',
            'products.unit',
            'products.units',
            'products.offers_data',
            'webpages.canonical_url as url',
            'products.offers_data as product_offers_data',
            'product_last_seens.webpage_id',
            'product_last_seens.last_seen_at',
        ]);

        return $queryBuilder
            ->orderByDesc('product_last_seens.last_seen_at')
            ->limit(self::MAX_PRODUCTS)
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->webpage->website_id === $this->website->id;
    }

    public function asController(Webpage $webpage, ActionRequest $request): Collection
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        $customer = $request->user()?->customer;
        $cookieId = $customer ? null : $request->cookie(StoreIrisProductLastSeen::COOKIE_NAME);

        return $this->handle($webpage, $customer, $cookieId);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductLastSeenResource::collection($products);
    }
}
