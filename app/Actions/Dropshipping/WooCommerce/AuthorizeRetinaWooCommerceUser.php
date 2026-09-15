<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\WooCommerce\Traits\WithWooCommerceAuthorizationToken;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthorizeRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWooCommerceAuthorizationToken;

    public $commandSignature = 'retina:ds:authorize-woo {customer} {name} {url}';

    public function handle(Customer $customer, $modelData): string
    {
        StoreTemporaryWooUser::run($customer, $modelData);

        $token = $this->storeWooAuthorizationToken([
            'customer_id' => $customer->id
        ]);

        $params = [
            'app_name' => 'AW Connect',
            'scope' => 'read_write',
            'user_id' => $token,
            'return_url' => route('retina.dropshipping.platform.woo_callback.success'),
            'callback_url' => route('webhooks.woo.callback')
        ];

        return Arr::get($modelData, 'url') . '/wc-auth/v1/authorize?' . http_build_query($params);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($request->filled('url')) {
            $this->set('url', rtrim(trim($request->input('url')), '/'));
        }
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                'regex:/^https:\/\//',
                function ($attribute, $value, $fail) {
                    $this->validateStoreIsReachable(rtrim($value, '/'), $fail);
                }
            ]
        ];
    }

    /**
     * @param \Closure(string):void $fail
     */
    protected function validateStoreIsReachable(string $storeUrl, \Closure $fail): void
    {
        $endpoint = $storeUrl . '/wp-json/wc/v3';

        try {
            $response = Http::timeout(120)
                ->connectTimeout(120)
                ->withHeaders([
                    'Accept'     => 'application/json',
                    'User-Agent' => 'WooCommerce AW Connect API Client-PHP/1.0',
                ])
                ->get($endpoint);
        } catch (\Throwable $e) {
            $this->logStoreCheckFailure($endpoint, $this->connectionFailureReason($e), ['exception' => $e::class, 'error' => $e->getMessage()]);

            $fail($this->connectionFailureMessage($e));

            return;
        }

        $body = $response->body();
        $wooErrorCode = Arr::get($response->json() ?? [], 'code');
        $isWooApi = is_string($wooErrorCode) && str_starts_with($wooErrorCode, 'woocommerce_rest_');

        $context = [
            'status'        => $response->status(),
            'effective_url' => (string)$response->effectiveUri(),
            'woo_code'      => $wooErrorCode,
            'body'          => substr($body, 0, 500),
        ];

        $effectiveHost = parse_url((string)$response->effectiveUri(), PHP_URL_HOST);
        $submittedHost = parse_url($storeUrl, PHP_URL_HOST);

        if ($isWooApi) {
            if ($effectiveHost && $submittedHost && $effectiveHost !== $submittedHost) {
                $this->logStoreCheckFailure($endpoint, 'redirected_to_other_host', $context);

                $fail(__('Your store redirects to :host, please enter that address as your store url.', ['host' => $effectiveHost]));
            }

            return;
        }

        if ($response->status() === 404) {
            $this->logStoreCheckFailure($endpoint, 'rest_api_not_found', $context);

            $fail(__('We could not find the WooCommerce API on this store, make sure WooCommerce is installed, its REST API is enabled and your WordPress permalinks are not set to Plain.'));

            return;
        }

        if (in_array($response->status(), [401, 403], true)) {
            $this->logStoreCheckFailure($endpoint, 'rest_api_blocked', $context);

            $fail(__('Your store answered with :status and blocked our request, this is usually a security plugin, a firewall or Cloudflare, please allow requests to /wp-json/.', ['status' => $response->status()]));

            return;
        }

        if ($response->serverError()) {
            $this->logStoreCheckFailure($endpoint, 'store_server_error', $context);

            $fail(__('Your WooCommerce store returned an error :status, please check your hosting error log and try again later.', ['status' => $response->status()]));

            return;
        }

        $this->logStoreCheckFailure($endpoint, 'unexpected_response', $context);

        $fail(__('Your store answered with :status but did not return the WooCommerce REST API, please check your store url points to the WordPress installation.', ['status' => $response->status()]));
    }

    protected function connectionFailureReason(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'could not resolve host'), str_contains($message, 'name or service not known') => 'dns_failure',
            str_contains($message, 'ssl certificate problem'), str_contains($message, 'certificate verify failed'), str_contains($message, 'ssl'), str_contains($message, 'tls') => 'ssl_failure',
            str_contains($message, 'connection refused') => 'connection_refused',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'too many redirects'), str_contains($message, 'redirect') => 'redirect_loop',
            default => 'connection_failure',
        };
    }

    protected function connectionFailureMessage(\Throwable $e): string
    {
        return match ($this->connectionFailureReason($e)) {
            'dns_failure' => __('We could not resolve your store domain, please check the store url is spelled correctly and the domain is live.'),
            'ssl_failure' => __('Your store SSL certificate could not be verified, it may be expired, self signed or incomplete, please renew it with your hosting provider.'),
            'connection_refused' => __('Your store refused our connection, please ask your hosting provider to allow requests to the WordPress REST API.'),
            'timeout' => __('Your store did not answer within 10 seconds, this is usually a firewall blocking our server or a very slow host.'),
            'redirect_loop' => __('Your store url redirects in a loop, please enter the final address of your store.'),
            default => __('Unable to connect to the WooCommerce store, please check your store url.'),
        };
    }

    protected function logStoreCheckFailure(string $endpoint, string $reason, array $context): void
    {
        Log::error('WooCommerce store authorisation check failed', array_merge([
            'endpoint'    => $endpoint,
            'reason'      => $reason,
            'customer_id' => request()->user() instanceof WebUser ? request()->user()->customer?->id : null,
        ], $context));
    }

    public function asController(ActionRequest $request): string
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function asCommand(Command $command): void
    {
        $modelData = [
            'name' => $command->argument('name'),
            'url' => rtrim(trim($command->argument('url')), '/'),
        ];

        $customer = Customer::findOrFail($command->argument('customer'));

        $command->info($this->handle($customer, $modelData));
    }
}
