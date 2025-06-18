<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\Accounting\OrgPaymentProvidersResource;
use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\QueryBuilder;

class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;


        $queryBuilderSp = QueryBuilder::for(PaymentServiceProvider::class);

        $queryBuilderSp->where('payment_service_providers.group_id', $product->organisation->group_id);

        $resultSp = $queryBuilderSp
            ->defaultSort('payment_service_providers.code')
            ->select([
                'org_payment_service_providers.slug',
                'payment_service_providers.code',
                'payment_service_providers.state',
                'name',
                'payment_service_providers.type',
                'payment_service_providers.id'
            ])
            ->leftJoin(
                'org_payment_service_providers',
                function ($leftJoin) use ($product) {
                    $leftJoin->on('payment_service_providers.id', '=', 'org_payment_service_providers.payment_service_provider_id')
                        ->where('org_payment_service_providers.organisation_id', '=', $product->organisation->id)
                        ->leftJoin('org_payment_service_provider_stats', 'org_payment_service_providers.id', 'org_payment_service_provider_stats.org_payment_service_provider_id');
                }
            )
            ->get();

        return [
            'slug'        => $product->slug,
            'code'        => $product->code,
            'name'        => $product->name,
            'description' => $product->description,
            'stock'       => $product->available_quantity,
            'contents'    => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'              => $product->id,
            'slug'            => $product->slug,
            'image_id'        => $product->image_id,
            'code'            => $product->code,
            'name'            => $product->name,
            'price'           => $product->price,
            'currency_code'   => $product->currency->code,
            'description'     => $product->description,
            'state'           => $product->state,
            'rrp'             => $product->rrp,
            'price'           => $product->price,
            'status'           => $product->status,
            'state'           => $product->state,
            'description'     => $product->description,
            'units'           => $product->units,
            'unit'            => $product->unit,
            'created_at'      => $product->created_at,
            'updated_at'      => $product->updated_at,
            'images'          => ImageResource::collection($product->images)->toArray($request),
            'service_providers' => OrgPaymentProvidersResource::collection($resultSp)->toArray($request),
            'tags' => TagResource::collection($product->tradeUnitTagsViaTradeUnits())->toArray($request),
            'return_policy' => Arr::get($product->webpage->website->settings, 'return_policy', ''),
        ];
    }
}
