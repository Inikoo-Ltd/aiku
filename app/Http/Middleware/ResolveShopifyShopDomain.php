<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Osiset\ShopifyApp\Objects\Values\SessionToken;
use Osiset\ShopifyApp\Util;
use Throwable;

class ResolveShopifyShopDomain
{
    /**
     * Osiset's ShopDomain::fromRequest() only looks at the "shop"/"shopDomain" input, the
     * X-Shop-Domain header and finally the Referer. It never reads "host", which is the parameter
     * that actually survives navigation inside the Shopify admin, so whenever "shop" drops off a
     * link or a redirect the resolution falls back to a stale, browser-dependent Referer, or to
     * nothing at all under a strict Referrer-Policy. This middleware restores "shop" from the
     * sources Shopify really guarantees before VerifyShopify runs.
     *
     * Requests carrying an hmac are left untouched: VerifyShopify rebuilds the signed payload from
     * the request inputs, so rewriting them would invalidate the signature.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->hasHmac($request)) {
            return $next($request);
        }

        $shopDomain = $this->normaliseShopDomain($request->input('shop', $request->input('shopDomain')))
            ?? $this->fromHostParameter($request)
            ?? $this->fromSessionToken($request);

        if ($shopDomain === null) {
            $this->forgetShopInput($request);

            return $next($request);
        }

        $request->merge(['shop' => $shopDomain]);

        return $next($request);
    }

    private function hasHmac(Request $request): bool
    {
        if ($request->input('hmac') !== null) {
            return true;
        }

        $refererQuery = parse_url((string) $request->header('referer'), PHP_URL_QUERY);

        return $refererQuery !== null && $refererQuery !== false
            && isset(Util::parseQueryString($refererQuery)['hmac']);
    }

    /**
     * A blank or unusable "shop" is not the same as a missing one: ShopDomain::fromRequest() stops
     * at the first non-null source, so "?shop=" or "?shop=kosmoi.nl" makes it build a domain from a
     * value it cannot accept and throw InvalidShopDomainException, instead of falling through to
     * the header and Referer and ultimately to the install redirect.
     */
    private function forgetShopInput(Request $request): void
    {
        foreach (['shop', 'shopDomain'] as $key) {
            $request->query->remove($key);
            $request->request->remove($key);
        }
    }

    /**
     * Shopify base64-encodes "host" as either "admin.shopify.com/store/<handle>" or the legacy
     * "<handle>.myshopify.com/admin".
     */
    private function fromHostParameter(Request $request): ?string
    {
        $host = $request->input('host');

        if (! is_string($host) || $host === '') {
            return null;
        }

        $decoded = base64_decode(strtr($host, '-_', '+/'), true);

        return $decoded === false ? null : $this->normaliseShopDomain($decoded);
    }

    private function fromSessionToken(Request $request): ?string
    {
        $token = $request->bearerToken() ?? $request->input('token', $request->input('id_token'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            return $this->normaliseShopDomain((new SessionToken($token, false))->getShopDomain()->toNative());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Accepts a bare handle, a "<handle>.myshopify.com" domain, a pasted admin URL, or anything
     * carrying a scheme or trailing path. Anything else, notably a merchant's custom storefront
     * domain, resolves to null: Shopify never accepts one as a "shop" value.
     */
    private function normaliseShopDomain(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $endDomain = Util::getShopifyConfig('myshopify_domain');
        $value     = strtolower(trim($value));
        $value     = preg_replace('#^[a-z]+://#', '', $value);
        $value     = trim(explode('?', $value)[0], " \t\n\r\0\x0B/");

        if (preg_match('#(?:^|/)admin\.shopify\.com/store/([a-z0-9][a-z0-9\-]*)#', $value, $matches)) {
            return $matches[1].'.'.$endDomain;
        }

        $host = explode('/', $value)[0];

        if (preg_match('/^[a-z0-9][a-z0-9\-]*$/', $host)) {
            return $host.'.'.$endDomain;
        }

        if (preg_match('/^[a-z0-9][a-z0-9\-]*\.'.preg_quote($endDomain, '/').'$/', $host)) {
            return $host;
        }

        return null;
    }
}
