<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductLastSeen extends IrisAction
{
    private ?string $cookieId = null;
    private Webpage $webpage;

    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId): LengthAwarePaginatorContract
    {
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
            'products.webpage_id',
            'webpages.canonical_url as url',
            'products.offers_data as product_offers_data',
            'product_last_seens.last_seen_at',
        ]);

        return $queryBuilder
            ->orderByDesc('product_last_seens.last_seen_at')
            ->withIrisPaginator(25)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->webpage->website_id !== $this->website->id;
    }
    
    public function asController(Webpage $webpage, ActionRequest $request): LengthAwarePaginatorContract
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        $customer = $request->user()?->customer;

        if (!$customer) {
            $this->cookieId = $request->cookie(StoreIrisProductLastSeen::COOKIE_NAME) ?: null;
        }

        return $this->handle($webpage, $customer, $this->cookieId);
    }

    public function jsonResponse(LengthAwarePaginatorContract $products): AnonymousResourceCollection
    {
        return IrisProductLastSeenResource::collection($products)->additional([
            'cookie_id' => $this->cookieId
        ]);
    }
}
