<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;


class IndexBasketProducts extends OrgAction
{
    public function handle(Order $order): Array
    {
        $query = DB::table('transactions')->where('transactions.order_id', $order->id)
                        ->where('transactions.model_type', 'Product')
                        ->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id')
                        ->leftjoin('products', 'assets.model_id', '=', 'products.id')
                        ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id');

        return $query->select([
                'transactions.id as transaction_id',
                'transactions.quantity_ordered as quantity_ordered',
                'products.id as product_id',
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
                'webpages.url',
                'webpages.canonical_url',
                'webpages.website_id',
                'webpages.id as webpage_id',
            ])
            ->orderBy('products.name') // Change sort, maybe by id to make sure it's the same as the one on basket page
            ->get()
            ->keyBy('product_id')
            ->toArray();
    }
}
