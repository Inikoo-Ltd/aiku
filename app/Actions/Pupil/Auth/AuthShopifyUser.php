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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Lorisleiva\Actions\Concerns\AsAction;
use Osiset\ShopifyApp\Actions\AuthenticateShop;
use Osiset\ShopifyApp\Exceptions\MissingAuthUrlException;
use Osiset\ShopifyApp\Exceptions\SignatureVerificationException;
use Osiset\ShopifyApp\Http\Controllers\AuthController;
use Osiset\ShopifyApp\Messaging\Events\ShopAuthenticatedEvent;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;

class AuthShopifyUser extends AuthController
{
    use AsAction;

    public function authenticate(Request $request, AuthenticateShop $authShop)
    {
        if ($request->missing('shop') && !$request->user()) {
            return null;
        }

        // Get the shop domain
        $shopDomain = $request->has('shop')
            ? ShopDomain::fromNative($request->input('shop'))
            : $request->user()->getDomain();

        // If the domain is obtained from $request->user()
        if ($request->missing('shop')) {
            $request['shop'] = $shopDomain->toNative();
        }

        // Run the action
        [$result, $status] = $authShop($request);

        if ($status === null) {
            $this->logAuthFailure('Invalid HMAC verification', $request, $shopDomain, $result);

            throw new SignatureVerificationException('Invalid HMAC verification');
        } elseif ($status === false) {
            if (!$result['url']) {
                $this->logAuthFailure('Missing auth url', $request, $shopDomain, $result);

                throw new MissingAuthUrlException('Missing auth url');
            }

            $shopDomain = $shopDomain->toNative();
            $shopOrigin = $shopDomain ?? $request->user()->name;

            event(new ShopAuthenticatedEvent($result['shop_id']));

            return View::make(
                'shopify-app::auth.fullpage_redirect',
                [
                    'apiKey' => Util::getShopifyConfig('api_key', $shopOrigin),
                    'url' => $result['url'],
                    'host' => $request->input('host'),
                    'shopDomain' => $shopDomain,
                    'locale' => $request->input('locale'),
                ]
            );
        } else {
            return Redirect::route(
                Util::getShopifyConfig('route_names.home'),
                [
                    'shop' => $shopDomain->toNative(),
                    'host' => $request->input('host'),
                    'locale' => $request->input('locale'),
                ]
            );
        }
    }

    /**
     * InstallShop swallows every exception from the token exchange and returns
     * ['completed' => false, 'url' => null], so a network blip, a 401, an HMAC mismatch and a
     * database constraint all surface here as the same opaque failure. Record what we do know
     * about the request so the intermittent cases can be told apart.
     *
     * @param array{url?: string|null, completed?: bool, shop_id?: mixed} $result
     */
    private function logAuthFailure(string $reason, Request $request, ShopDomain $shopDomain, array $result): void
    {
        Log::warning('Shopify authenticate failed: '.$reason, [
            'shop'          => $shopDomain->toNative(),
            'shop_source'   => $request->has('shop') ? 'request' : 'user',
            'has_host'      => $request->filled('host'),
            'has_hmac'      => $request->filled('hmac'),
            'has_code'      => $request->filled('code'),
            'has_timestamp' => $request->filled('timestamp'),
            'result_url'    => $result['url'] ?? null,
            'shop_id'       => $result['shop_id'] ?? null,
        ]);
    }

    public function token(Request $request)
    {
        $request->session()->reflash();
        $shopDomain = ShopDomain::fromRequest($request);
        $target = $request->query('target');
        $query = parse_url($target, PHP_URL_QUERY);


        if ($query) {
            // remove "token" from the target's query string
            $params = Util::parseQueryString($query);
            $params['shop'] = $params['shop'] ?? $shopDomain->toNative() ?? '';
            $params['host'] = $request->input('host');
            $params['locale'] = $request->input('locale');
            unset($params['token']);

        } else {
            $params = [
                'shop' => $shopDomain->toNative() ?? '',
                'host' => $request->input('host'),
                'locale' => $request->input('locale'),
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
