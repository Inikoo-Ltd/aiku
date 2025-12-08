<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $hmacHeader = $request->header('x-shopify-hmac-sha256');
        $data = $request->getContent();
        $secret = config('shopify-app.api_secret');

        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

        if (! hash_equals($hmacHeader, $calculatedHmac)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
