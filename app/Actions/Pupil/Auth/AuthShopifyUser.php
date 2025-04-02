<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 15-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

namespace App\Actions\Pupil\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Lorisleiva\Actions\Concerns\AsAction;
use Osiset\ShopifyApp\Http\Controllers\AuthController;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;

class AuthShopifyUser extends AuthController
{
    use AsAction;

    public function token(Request $request)
    {
        $request->session()->reflash();
        $shopDomain = ShopDomain::fromRequest($request);
        $target = $request->query('target');
        $query = parse_url($target, PHP_URL_QUERY);

        $cleanTarget = $target;
        if ($query) {
            // remove "token" from the target's query string
            $params = Util::parseQueryString($query);
            $params['shop'] = $params['shop'] ?? $shopDomain->toNative() ?? '';
            $params['host'] = $request->get('host');
            $params['locale'] = $request->get('locale');
            unset($params['token']);

        } else {
            $params = [
                'shop' => $shopDomain->toNative() ?? '',
                'host' => $request->get('host'),
                'locale' => $request->get('locale'),
            ];
        }

        $cleanTarget = trim(explode('?', $target)[0].'?'.http_build_query($params), '?');

        return View::make(
            'shopify.token',
            [
                'shopDomain' => $shopDomain->toNative(),
                'target' => $cleanTarget,
            ]
        );
    }
}
