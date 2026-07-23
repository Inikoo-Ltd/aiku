<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\External\Faire\GetFaireOrdersInShop;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Http;

test('follows faire cursor pagination until exhausted', function () {
    Http::fake(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
        if ($query['cursor'] ?? null) {
            return Http::response(['orders' => [], 'cursor' => null]);
        }

        return Http::response(['orders' => [], 'cursor' => 'next-page-cursor']);
    });

    $shop = new Shop();
    $shop->settings = ['faire' => ['access_token' => 'test-token']];

    GetFaireOrdersInShop::make()->handle($shop);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return ($query['cursor'] ?? null) === 'next-page-cursor'
            && !isset($query['excluded_states']);
    });
});
