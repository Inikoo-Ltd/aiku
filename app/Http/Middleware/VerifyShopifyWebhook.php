<?php

namespace App\Http\Middleware;

use App\Models\Dropshipping\ShopifyUser;
use Closure;
use Illuminate\Http\Request;

class VerifyShopifyWebhook
{
    /**
     * Shopify sends the fulfilment service stock lookup as an unsigned GET, so it cannot be
     * verified. It only reads the store's own cached stock levels and writes nothing.
     */
    private const array UNSIGNED_ROUTES = [
        'webhooks.shopify.fetch_stock',
    ];

    /**
     * Shopify signs every webhook body with the app secret; anything else is refused.
     */
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->route()?->getName(), self::UNSIGNED_ROUTES, true)) {
            return $next($request);
        }

        if (!$this->hasValidSignature($request) || !$this->shopMatchesRoute($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    /**
     * A valid signature only proves Shopify sent the call, not that it concerns the shopify user
     * named in the URL, so the shop domain Shopify stamps on the request must match that user.
     */
    protected function shopMatchesRoute(Request $request): bool
    {
        $shopifyUser = $request->route('shopifyUser');

        if (!$shopifyUser instanceof ShopifyUser) {
            return true;
        }

        /* Deleting a shopify user renames it to a ulid, so a store that left can no longer match;
           the signature still proves Shopify sent it, and the handler answers it with a 200. */
        if ($shopifyUser->trashed()) {
            return true;
        }

        return $request->header('x-shopify-shop-domain') === $shopifyUser->name;
    }

    protected function hasValidSignature(Request $request): bool
    {
        $hmacHeader = $request->header('x-shopify-hmac-sha256');

        if (!is_string($hmacHeader) || $hmacHeader === '') {
            return false;
        }

        $calculatedHmac = base64_encode(
            hash_hmac('sha256', $request->getContent(), (string) config('shopify-app.api_secret'), true)
        );

        return hash_equals($calculatedHmac, $hmacHeader);
    }
}
