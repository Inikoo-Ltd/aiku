<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Fulfilment\PhysicalGoodsResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Product;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Traits\HasSearchableText;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetFulfilmentPhysicalGoods extends OrgAction
{
    use HasSearchableText;

    public function handle(Fulfilment $parent, PalletDelivery|PalletReturn|RecurringBill|Invoice $scope): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $normalizedValue = $this->normalizeSearchableText($value);

                // Ignore if search token is less than 2 words
                $searchTokens = array_values(array_filter(
                    explode(' ', trim($normalizedValue)),
                    fn ($t) => strlen($t) >= 2
                ));

                foreach ($searchTokens as $searchToken) {
                    $query->where('searchable_text', 'ILIKE', "% {$searchToken}%");
                }
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->shop_id);
        $queryBuilder->join('assets', 'products.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'products.currency_id', '=', 'currencies.id');

        if ($scope instanceof PalletDelivery || $scope instanceof PalletReturn) {
            $queryBuilder->whereNotIn('products.asset_id', $scope->services()->pluck('asset_id'));
        } elseif ($scope instanceof RecurringBill) {
            $queryBuilder->whereNotIn('products.asset_id', $scope->transactions()->pluck('asset_id'));
        } elseif ($scope instanceof Invoice) {
            $queryBuilder->whereNotIn('products.asset_id', $scope->invoiceTransactions()->pluck('asset_id'));
        }



        $queryBuilder
            ->defaultSort('products.id')
            ->select([
                'products.id',
                'products.slug',
                'products.name',
                'products.code',
                'products.state',
                'products.created_at',
                'products.price',
                'products.unit',
                'currencies.code as currency_code',
                'assets.current_historic_asset_id as historic_asset_id',

            ]);


        return $queryBuilder->allowedSorts(['id','code','name','price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    //todo review this
    public function authorize(ActionRequest $request): bool
    {
        if ($request->user() instanceof WebUser) {
            return true;
        }

        $this->canEdit   = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        $this->canDelete = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }

    public function inPalletDelivery(Fulfilment $fulfilment, PalletDelivery $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $scope);
    }

    public function inPalletReturn(Fulfilment $fulfilment, PalletReturn $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $scope);
    }

    public function inRecurringBill(Fulfilment $fulfilment, RecurringBill $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $scope);
    }

    public function inInvoice(Fulfilment $fulfilment, Invoice $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $scope);
    }

    public function jsonResponse(LengthAwarePaginator $physicalGoods): AnonymousResourceCollection
    {
        return PhysicalGoodsResource::collection($physicalGoods);
    }
}
