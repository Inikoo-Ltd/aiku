<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductLastSeen extends IrisAction
{
    private Webpage $webpage;

    public function handle(Webpage $webpage, array $modelData)
    {
        $queryBuilder = QueryBuilder::for(Product::class)
            ->join('product_last_seens', 'product_last_seens.webpage_id', '=', 'products.webpage_id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->where('product_last_seens.shop_id', $webpage->shop_id)
            ->where(function ($query) use ($modelData) {
                $query->where('customer_id', '=', Arr::get($modelData, 'user_id'))
                    ->orWhere('cookie_id', '=', Arr::get($modelData, 'user_id'));
            })
            ->where('product_last_seens.webpage_id', '=', $webpage->id);

        $queryBuilder->select([
            'products.id',
            'products.code',
            'products.name',
            'webpages.slug',
            'product_last_seens.last_seen_at',
        ]);

        return $queryBuilder->first();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->webpage->website_id !== $this->website->id;
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        return $this->handle($webpage, $request->all());
    }

    public function jsonResponse($lastSeen): AnonymousResourceCollection
    {
        return IrisProductLastSeenResource::make($lastSeen);
    }
}
