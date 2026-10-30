<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use App\Actions\Dropshipping\Wix\User\CheckWixChannel;
use App\Actions\Dropshipping\Wix\User\SaveShopDataWixChannel;
use App\Actions\Dropshipping\Wix\User\UpdateWixUser;
use App\Models\Dropshipping\WixUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

/**
 * Wix reports every installation of the app on a site here. Since OAuth apps register no
 * redirect URI, this is the only moment we learn an instance id, so the instance is recorded
 * even when we cannot yet tell which customer it belongs to.
 *
 * @see https://dev.wix.com/docs/rest/app-management/app-instance/app-instance-installed
 */
class HandleWixAppInstanceInstalled
{
    use AsAction;
    use WithWixWebhookVerification;

    public const string EVENT_TYPE = 'AppInstalled';

    /**
     * The install webhook and the OAuth callback both look an instance up and create it when
     * missing, and Wix sends them within the same second, as it can send two callbacks. Without
     * a shared lock each misses the other's row and the instance ends up with two (PRE-43).
     */
    public static function lockInstance(string $instanceId, callable $callback): mixed
    {
        // ponytail: the callback holds this through several Wix calls with no timeout of their own, hence the 180s TTL; the webhook waits up to 20s
        return Cache::lock('wix-instance:'.$instanceId, 180)->block(20, $callback);
    }

    public function handle(string $instanceId, array $eventData = []): ?WixUser
    {
        $wixUser = self::lockInstance($instanceId, function () use ($instanceId, $eventData) {
            // Deleted users are left deleted: one means the customer closed that channel, and
            // reviving it would attach this install to a closed channel. A reinstall starts fresh.
            $wixUser = WixUser::where('wix_instance_id', $instanceId)
                ->orderByRaw('customer_id IS NULL')
                ->orderBy('id')
                ->first();

            if (!$wixUser) {
                return WixUser::create([
                    'wix_instance_id' => $instanceId,
                    'name'            => $instanceId,
                    'status'          => true,
                    'data'            => [
                        'origin_instance_id' => Arr::get($eventData, 'originInstanceId'),
                        'installed_at'       => now()->toIso8601String(),
                    ],
                ]);
            }

            // A reinstall invalidates whatever token we had cached for the instance.
            return UpdateWixUser::make()->handle($wixUser, [
                'access_token'           => null,
                'access_token_expire_in' => null,
            ], false);
        });

        SaveShopDataWixChannel::run($wixUser);

        if ($wixUser->customerSalesChannel) {
            CheckWixChannel::run($wixUser->fresh());
        }

        return $wixUser->fresh();
    }

    /**
     * Wix expects a 200 within 1250ms and retries otherwise, so failures are swallowed after
     * being reported rather than surfaced as a non-200.
     */
    public function asController(Request $request): JsonResponse
    {
        $event = $this->verifyWixWebhook($request->getContent());

        if (!$event) {
            return response()->json(['message' => 'Invalid Wix webhook signature'], 400);
        }

        $instanceId = Arr::get($event, 'instanceId');

        if ($instanceId) {
            try {
                $this->handle($instanceId, Arr::get($event, 'data', []));
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }

        return response()->json(['received' => true]);
    }
}
