<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\FulfilmentService\StoreFulfilmentService;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Osiset\ShopifyApp\Objects\Values\SessionToken;

/**
 * A store is linked to a customer only after that customer completed Shopify's OAuth for it: retina hands
 * out a claim, pupil keeps it in its session while Shopify authorises and turns it into a proof when the
 * install completes, and the proof is redeemed in retina by the same logged-in customer. A store name alone
 * is never enough, and a claim link sent to a merchant cannot link their store to someone else.
 */
class ClaimShopifyUser extends RetinaAction
{
    private const string SESSION_KEY = 'shopify_claim';

    public function handle(Customer $customer, string $shopDomain): ShopifyUser
    {
        abort_if(ShopifyUser::where('name', $shopDomain)->whereNotNull('customer_id')->where('customer_id', '!=', $customer->id)->exists(), 403);

        $shopifyUser = StoreShopifyUser::make()->handle($customer, ['name' => Str::before($shopDomain, '.'.config('shopify-app.my_shopify_domain'))]);

        CheckShopifyChannel::run($shopifyUser->customerSalesChannel);
        StoreFulfilmentService::run($shopifyUser->customerSalesChannel);

        return $shopifyUser;
    }

    public function authenticateUrl(Customer $customer, string $shopDomain): string
    {
        return route('pupil.authenticate', [
            'shop'  => $shopDomain,
            'claim' => Crypt::encrypt($this->token('claim', $customer->id, $shopDomain, 60)),
        ]);
    }

    public function rememberClaim(Request $request, string $shopDomain): void
    {
        $claim = $this->read($request->input('claim'), 'claim');

        if ($claim && $claim['shop'] === $shopDomain) {
            $request->session()->put(self::SESSION_KEY, $claim);
        }
    }

    /**
     * Legacy rows were given to whoever typed the store name. When Shopify really installs such a row that never
     * held a token it is retired like a deleted one, whoever started the flow, so the install writes its token on
     * a new row no channel can reach; the customer who proves the store gets their old channel back by reference.
     */
    public function retireUnprovenOwner(Request $request, string $shopDomain): void
    {
        if (!$this->isGenuineInstall($request, $shopDomain)) {
            return;
        }

        ShopifyUser::withTrashed()
            ->where('name', $shopDomain)
            ->whereNotNull('customer_id')
            ->where(fn ($query) => $query->whereNull('password')->orWhere('password', 'not like', 'shpat_%'))
            ->get()
            ->each(function (ShopifyUser $shopifyUser) {
                $ulid = (string) Str::ulid();
                $data = $shopifyUser->data;
                data_set($data, 'original_data', ['name' => $shopifyUser->name, 'email' => $shopifyUser->email, 'slug' => $shopifyUser->slug]);

                $shopifyUser->update(['name' => $ulid, 'slug' => $ulid, 'email' => $ulid, 'status' => false, 'data' => $data]);

                CustomerSalesChannel::where('platform_user_type', class_basename($shopifyUser))
                    ->where('platform_user_id', $shopifyUser->id)
                    ->where('status', '!=', CustomerSalesChannelStatusEnum::CLOSED)
                    ->get()
                    ->each(fn (CustomerSalesChannel $customerSalesChannel) => UpdateCustomerSalesChannel::run($customerSalesChannel, ['status' => CustomerSalesChannelStatusEnum::CLOSED]));

                if (!$shopifyUser->trashed()) {
                    $shopifyUser->delete();
                }
            });
    }

    /**
     * Only the query string is signed: the OAuth callback by its hmac, which the VerifyShopify middleware checks,
     * and a token exchange by a session token signed with our secret. A recent callback for this very store, or
     * a valid session token for it, is an install; anything else, including values sent in the body, is not.
     */
    private function isGenuineInstall(Request $request, string $shopDomain): bool
    {
        if (Str::lower((string) $request->query('shop')) !== $shopDomain) {
            return false;
        }

        if ($request->query('code')) {
            return $request->query('hmac') && abs(now()->timestamp - (int) $request->query('timestamp')) <= 600;
        }

        try {
            return (new SessionToken((string) $request->query('id_token')))->getShopDomain()->toNative() === $shopDomain;
        } catch (\Throwable) {
            return false;
        }
    }

    public function proofUrl(Request $request, string $shopDomain): ?string
    {
        $claim = $this->claimFor($request, $shopDomain);

        if (!$claim) {
            return null;
        }

        $request->session()->forget(self::SESSION_KEY);

        $customer = Customer::find($claim['customer_id']);
        if (!$customer?->shop?->website) {
            return null;
        }

        $routeName = $customer->shop->type === ShopTypeEnum::FULFILMENT
            ? 'retina.fulfilment.dropshipping.customer_sales_channels.shopify_user.claim'
            : 'retina.dropshipping.platform.shopify_user.claim';

        return 'https://'.$customer->shop->website->domain.route($routeName, [
            'proof' => Crypt::encrypt($this->token('proof', $customer->id, $shopDomain, 10)),
        ], false);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);

        $proof = $this->read($request->query('proof'), 'proof');

        abort_unless($proof && $proof['customer_id'] === $this->customer?->id, 403);

        $shopifyUser = $this->handle($this->customer, $proof['shop']);

        return redirect()->route(
            $this->shop->type === ShopTypeEnum::FULFILMENT ? 'retina.fulfilment.dropshipping.customer_sales_channels.show' : 'retina.dropshipping.customer_sales_channels.show',
            $shopifyUser->customerSalesChannel->slug
        );
    }

    private function claimFor(Request $request, string $shopDomain): ?array
    {
        $claim = $request->session()->get(self::SESSION_KEY);

        if (!is_array($claim) || $claim['shop'] !== $shopDomain || $claim['expires_at'] < now()->timestamp) {
            return null;
        }

        return $claim;
    }

    /**
     * @return array{purpose: string, customer_id: int, shop: string, expires_at: int}
     */
    private function token(string $purpose, int $customerId, string $shopDomain, int $minutes): array
    {
        return [
            'purpose'     => $purpose,
            'customer_id' => $customerId,
            'shop'        => $shopDomain,
            'expires_at'  => now()->addMinutes($minutes)->timestamp,
        ];
    }

    private function read(mixed $encrypted, string $purpose): ?array
    {
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $token = Crypt::decrypt($encrypted);
        } catch (DecryptException) {
            return null;
        }

        if (!is_array($token) || ($token['purpose'] ?? null) !== $purpose || ($token['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $token;
    }
}
