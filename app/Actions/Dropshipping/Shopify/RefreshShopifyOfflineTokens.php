<?php

namespace App\Actions\Dropshipping\Shopify;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Osiset\ShopifyApp\Exceptions\ApiException;
use Osiset\ShopifyApp\Services\OfflineAccessTokenRefresher;
use Throwable;

class RefreshShopifyOfflineTokens
{
    use AsAction;
    use WithActionUpdate;

    public string $commandSignature = 'shopify:refresh-offline-tokens';

    /**
     * Renews the store's refresh token before it runs out. A store that uninstalled us or revoked the token
     * answers 400 or 401: that is the merchant's doing, not a fault, so the channel is shown as disconnected
     * instead of being reported every night until the token expires.
     *
     * @throws Throwable when the renewal failed for any other reason
     */
    public function handle(ShopifyUser $shopifyUser): bool
    {
        try {
            app(OfflineAccessTokenRefresher::class)->refreshIfNeeded($shopifyUser);
        } catch (ApiException $exception) {
            if (!$this->storeRevokedOurToken($exception)) {
                throw $exception;
            }

            if ($shopifyUser->customerSalesChannel) {
                $this->update($shopifyUser->customerSalesChannel, [
                    'platform_status'         => false,
                    'can_connect_to_platform' => false,
                    'exist_in_platform'       => false,
                ]);
            }

            return false;
        }

        return true;
    }

    private function storeRevokedOurToken(ApiException $exception): bool
    {
        $previous = $exception->getPrevious();

        return $previous instanceof ClientException && in_array($previous->getResponse()->getStatusCode(), [400, 401], true);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $now     = Carbon::now();
        $renewed = 0;
        $revoked = 0;
        $failed  = 0;

        ShopifyUser::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->whereNotNull('shopify_offline_refresh_token')
            ->where('shopify_offline_refresh_token', '!=', '')
            ->whereBetween('shopify_offline_refresh_token_expires_at', [$now, $now->copy()->addDays((int) config('shopify-app.offline_refresh_token_renewal_days'))])
            ->chunkById(100, function ($shopifyUsers) use (&$renewed, &$revoked, &$failed, $command) {
                foreach ($shopifyUsers as $shopifyUser) {
                    try {
                        $this->handle($shopifyUser) ? $renewed++ : $revoked++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $command->error("$shopifyUser->name: {$exception->getMessage()}");
                        $failed++;
                    }
                }
            });

        $command->info("Renewed $renewed, revoked by the store $revoked, failed $failed.");

        return 0;
    }
}
