<?php

namespace App\Actions\Retina\Traits;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

trait WithRetinaProductsList
{
    protected function getRetinaProductsGlobalSearch(): AllowedFilter
    {
        return AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereAnyWordStartWith('products.name', $value);
            });
        });
    }

    protected function getRetinaProductsListQuery(Customer $customer): QueryBuilder
    {
        $basket = $customer->orderInBasket;

        $query  = QueryBuilder::for(Product::class);
        $select = [];

        if ($basket) {
            $query->leftJoin('transactions', function ($join) use ($basket) {
                $join->on('products.id', '=', 'transactions.model_id')
                    ->where('transactions.model_type', '=', 'Product')
                    ->where('transactions.order_id', '=', $basket->id)
                    ->whereNull('transactions.deleted_at');
            });
            $select[] = 'transactions.id as transaction_id';
            $select[] = 'transactions.quantity_ordered as quantity_ordered';
        }

        $query->leftJoin('webpages', function ($join) {
            $join->on('products.id', '=', 'webpages.model_id')
                ->where('webpages.model_type', '=', 'Product');
        });

        $select = array_merge($select, [
            'products.id',
            'products.image_id',
            'products.code',
            'products.group_id',
            'products.organisation_id',
            'products.shop_id',
            'products.name',
            'products.available_quantity',
            'products.price',
            'products.rrp',
            'products.state',
            'products.status',
            'products.created_at',
            'products.updated_at',
            'products.units',
            'products.unit',
            'products.top_seller',
            'products.web_images',
            'products.slug',
            'webpages.canonical_url',
            'products.offers_data as product_offers_data',
        ]);

        return $query->select($select);
    }
}
