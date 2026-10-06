<?php

namespace App\Http\Middleware;

use App\Models\Dropshipping\ShopifyUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyShopifyWebhook
{
    /**
     * Routes subscribed after signatures were checked from the start have no unsigned traffic to
     * break, so they always refuse a bad signature. A forged products-deleted call would unlink a
     * merchant's products.
     */
    private const array ALWAYS_ENFORCED_ROUTES = [
        'webhooks.shopify.products_deleted',
    ];

    /**
     * Shopify signs every webhook body with the app secret. Routes that were never given this
     * middleware accepted anonymous callers, so while app.enforce_webhook_signatures is off an
     * unsigned call is recorded and allowed through: the log says whether any real traffic would
     * break before the rejection is switched on.
     */
    public function handle(Request $request, Closure $next)
    {
        $refusal = match (true) {
            !$this->hasValidSignature($request)   => 'signature',
            !$this->shopMatchesRoute($request)    => 'shop mismatch',
            default                               => null,
        };

        if (!$refusal) {
            return $next($request);
        }

        if (!config('app.enforce_webhook_signatures') && !in_array($request->route()?->getName(), self::ALWAYS_ENFORCED_ROUTES, true)) {
            Log::warning('Unverified Shopify webhook allowed', [
                'reason' => $refusal,
                'route'  => $request->route()?->getName(),
                'path'   => $request->path(),
                'ip'     => $request->ip(),
                'shop'   => $request->header('x-shopify-shop-domain'),
                'signed' => $request->hasHeader('x-shopify-hmac-sha256'),
            ]);

            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
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
