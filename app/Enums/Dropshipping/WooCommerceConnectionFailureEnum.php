<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Throwable;

enum WooCommerceConnectionFailureEnum: string
{
    use EnumHelperTrait;

    case REFUSED = 'refused';
    case FIREWALL = 'firewall';
    case TIMEOUT = 'timeout';
    case DNS = 'dns';
    case TLS = 'tls';
    case REDIRECTED = 'redirected';
    case NOT_WOOCOMMERCE = 'not_woocommerce';
    case CREDENTIALS = 'credentials';
    case STORE_ERROR = 'store_error';
    case UNKNOWN = 'unknown';

    private const string CHALLENGE_PATTERN = '/cf-chl|challenge-platform|just a moment|checking your browser|ddos protection|attention required|sgcaptcha|bot verification|imunify|wordfence|mod_security/i';

    public static function labels(): array
    {
        return [
            'refused'         => __('Connection refused'),
            'firewall'        => __('Blocked by a firewall'),
            'timeout'         => __('No answer'),
            'dns'             => __('Domain not found'),
            'tls'             => __('Certificate error'),
            'redirected'      => __('Redirects elsewhere'),
            'not_woocommerce' => __('No WooCommerce API'),
            'credentials'     => __('Key not accepted'),
            'store_error'     => __('Store error'),
            'unknown'         => __('Unknown'),
        ];
    }

    /**
     * Only a refused connection or a firewall reply is the store turning us away. A timeout can be
     * a firewall dropping packets or just a dead host, so it is not counted.
     */
    public function isBlocked(): bool
    {
        return $this === self::REFUSED || $this === self::FIREWALL;
    }

    public static function fromThrowable(Throwable $e): self
    {
        $message = strtolower($e->getMessage());

        if (preg_match('/curl error (\d+)/', $message, $matches)) {
            $byCurlCode = match ((int) $matches[1]) {
                6 => self::DNS,
                7 => self::REFUSED,
                28 => self::TIMEOUT,
                35, 51, 58, 60, 77, 83, 90, 91 => self::TLS,
                47 => self::REDIRECTED,
                default => null,
            };

            if ($byCurlCode) {
                return $byCurlCode;
            }
        }

        return match (true) {
            str_contains($message, 'could not resolve host'), str_contains($message, 'name or service not known') => self::DNS,
            str_contains($message, 'ssl'), str_contains($message, 'tls'), str_contains($message, 'certificate') => self::TLS,
            str_contains($message, 'connection refused'), str_contains($message, "couldn't connect to server") => self::REFUSED,
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => self::TIMEOUT,
            str_contains($message, 'redirect') => self::REDIRECTED,
            default => self::UNKNOWN,
        };
    }

    /**
     * Reads an authenticated API reply that was not a success. A WooCommerce error code means the
     * store answered and turned the key down; a challenge page or a 401/403/429 from anything else
     * is a security layer in front of the API.
     */
    public static function fromResponse(Response $response, string $requestedUrl): self
    {
        $status = $response->status();
        $body   = $response->body();
        $json   = json_decode(ltrim($body, "\xEF\xBB\xBF \t\n\r"), true);

        $requestedHost = parse_url($requestedUrl, PHP_URL_HOST);
        foreach ($response->toPsrResponse()->getHeader('X-Guzzle-Redirect-History') as $hop) {
            $hopHost = parse_url($hop, PHP_URL_HOST);
            if ($hopHost && $requestedHost && strcasecmp($hopHost, $requestedHost) !== 0) {
                return self::REDIRECTED;
            }
        }

        if (is_array($json)) {
            $code = (string) Arr::get($json, 'code', '');

            return match (true) {
                $code === 'rest_no_route' => self::NOT_WOOCOMMERCE,
                str_starts_with($code, 'woocommerce_rest_') && in_array($status, [400, 401, 403], true) => self::CREDENTIALS,
                in_array($status, [401, 403, 429], true) => self::FIREWALL,
                $status === 404 => self::NOT_WOOCOMMERCE,
                $status >= 500 => self::STORE_ERROR,
                default => self::UNKNOWN,
            };
        }

        if (($status === 202 || $status >= 500) && preg_match(self::CHALLENGE_PATTERN, $body)) {
            return self::FIREWALL;
        }

        return match (true) {
            $status >= 300 && $status < 400 => self::REDIRECTED,
            in_array($status, [401, 403, 406, 429], true) => self::FIREWALL,
            $status >= 500 => self::STORE_ERROR,
            $status >= 200 && $status < 500 => self::NOT_WOOCOMMERCE,
            default => self::UNKNOWN,
        };
    }

    public function customerMessage(): string
    {
        $ips = config('app.outgoing_ips');

        return match ($this) {
            self::REFUSED => __('Your store refused our connection because its hosting or firewall is blocking our servers. Ask your hosting provider to allow our IP addresses: :ips', ['ips' => $ips]),
            self::FIREWALL => __('A security plugin, a firewall or Cloudflare on your store is blocking our requests. Ask your hosting provider or developer to allow our IP addresses to reach /wp-json/: :ips', ['ips' => $ips]),
            self::TIMEOUT => __('Your store did not answer in time. This is usually a firewall blocking our servers or a very slow host. Ask your hosting provider to allow our IP addresses: :ips', ['ips' => $ips]),
            self::DNS => __('Your store domain no longer exists. If your store moved to a new address, reconnect the channel with that address.'),
            self::TLS => __('Your store SSL certificate could not be verified, it may be expired, self signed or incomplete, please renew it with your hosting provider.'),
            self::REDIRECTED => __('Your store address now redirects to another address. Reconnect the channel using the address your store uses now.'),
            self::NOT_WOOCOMMERCE => __('We could not find the WooCommerce API on this store, make sure WooCommerce is installed, its REST API is enabled and your WordPress permalinks are not set to Plain.'),
            self::CREDENTIALS => __('Your store did not accept our access key, it may have been deleted or lost its permissions. Reconnect the channel to create a new key.'),
            self::STORE_ERROR => __('Your WooCommerce store returned an error, please check your hosting error log and try again later.'),
            self::UNKNOWN => __('Your channel is not connected to your store. Try to reconnect it.'),
        };
    }
}
