<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 15:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\CancelPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Fulfilment\PalletReturn\SetPalletReturnAsPicked;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemInPalletReturnWithStoredItem;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * A fulfilment customer's TikTok orders packed outside aiku follow TikTok: an order TikTok shows as
 * sent is picked from the pallets it was allocated to and dispatched, one TikTok cancelled is
 * cancelled. Only for channels with settings.sync_fulfilment_orders_from_platform on.
 */
class SyncTiktokFulfilmentOrderStatus
{
    use AsAction;

    public const string SETTING = 'sync_fulfilment_orders_from_platform';

    public const array SENT_STATUSES = ['IN_TRANSIT', 'DELIVERED', 'COMPLETED'];

    public const array OPEN_STATES = [
        PalletReturnStateEnum::SUBMITTED,
        PalletReturnStateEnum::CONFIRMED,
        PalletReturnStateEnum::PICKING,
        PalletReturnStateEnum::PICKED,
    ];

    private const int TIKTOK_IDS_PER_CALL = 20;

    public string $commandSignature = 'tiktok:sync-fulfilment-orders';

    public static function isOn(?CustomerSalesChannel $customerSalesChannel): bool
    {
        return (bool) Arr::get($customerSalesChannel?->settings ?? [], self::SETTING, false);
    }

    public function handle(TiktokUser $tiktokUser, array $order): void
    {
        $status          = Arr::get($order, 'status');
        $platformOrderId = Arr::get($order, 'id');

        if (blank($platformOrderId) || !in_array($status, [...self::SENT_STATUSES, 'CANCELLED'], true) || !self::isOn($tiktokUser->customerSalesChannel)) {
            return;
        }

        $lock = Cache::lock('tiktok_order_'.$tiktokUser->id.'_'.$platformOrderId, 120);

        try {
            $lock->block(15, function () use ($tiktokUser, $platformOrderId, $status) {
                $palletReturn = PalletReturn::where('customer_sales_channel_id', $tiktokUser->customer_sales_channel_id)
                    ->where('platform_order_id', $platformOrderId)
                    ->whereIn('state', self::OPEN_STATES)
                    ->first();

                if (!$palletReturn) {
                    return;
                }

                if ($status === 'CANCELLED') {
                    CancelPalletReturn::make()->action($palletReturn->fulfilmentCustomer, $palletReturn);
                } elseif ($palletReturn->is_shipping_by_external) {
                    DB::transaction(fn () => $this->dispatchSent($palletReturn));
                }
            });
        } catch (LockTimeoutException) {
            return;
        } catch (Throwable $exception) {
            Sentry::captureException($exception);
        }
    }

    private function dispatchSent(PalletReturn $palletReturn): void
    {
        if (!in_array($palletReturn->state, [PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED], true)) {
            $palletReturn = PickingPalletReturn::make()->action($palletReturn);
        }

        $short = $palletReturn->items()
            ->where('type', 'StoredItem')
            ->whereNull('pallet_stored_item_id')
            ->with('storedItem')
            ->get()
            ->map(fn (PalletReturnItem $palletReturnItem) => sprintf('%s: 0 of %s, no pallet', $palletReturnItem->storedItem?->reference, (float) $palletReturnItem->quantity_ordered + 0))
            ->all();

        if ($palletReturn->items()->doesntExist()) {
            $short[] = __('the order had no lines in aiku');
        }

        $palletReturn->items()
            ->where('type', 'StoredItem')
            ->whereNotNull('pallet_stored_item_id')
            ->whereNotIn('state', [PalletReturnItemStateEnum::PICKED, PalletReturnItemStateEnum::NOT_PICKED, PalletReturnItemStateEnum::CANCEL])
            ->with(['storedItem', 'palletStoredItem.pallet'])
            ->get()
            ->each(function (PalletReturnItem $palletReturnItem) use (&$short) {
                $picked = PickPalletReturnItemInPalletReturnWithStoredItem::make()->action($palletReturnItem, [
                    'quantity_picked' => (float) $palletReturnItem->quantity_ordered,
                ]);

                if ((float) $picked->quantity_picked < (float) $palletReturnItem->quantity_ordered) {
                    $short[] = sprintf(
                        '%s: %s of %s on pallet %s',
                        $palletReturnItem->storedItem?->reference,
                        (float) $picked->quantity_picked + 0,
                        (float) $palletReturnItem->quantity_ordered + 0,
                        $palletReturnItem->palletStoredItem?->pallet?->reference
                    );
                }
            });

        if ($palletReturn->refresh()->state !== PalletReturnStateEnum::PICKED) {
            SetPalletReturnAsPicked::run($palletReturn);
        }

        $note = __('Sent on TikTok, dispatched by itself').($short ? '. '.__('aiku had less stock than sent').': '.implode('; ', $short) : '');
        UpdatePalletReturn::run($palletReturn, [
            'internal_notes' => trim($palletReturn->internal_notes."\n".$note),
        ]);

        DispatchPalletReturn::make()->action($palletReturn->refresh());
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $channels = CustomerSalesChannel::whereHas('platform', fn ($query) => $query->where('type', PlatformTypeEnum::TIKTOK))
            ->where('settings->'.self::SETTING, true)
            ->get();

        foreach ($channels as $customerSalesChannel) {
            /** @var TiktokUser|null $tiktokUser */
            $tiktokUser = $customerSalesChannel->user;
            if (!$tiktokUser) {
                continue;
            }

            PalletReturn::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereIn('state', self::OPEN_STATES)
                ->whereNotNull('platform_order_id')
                ->pluck('platform_order_id')
                ->chunk(self::TIKTOK_IDS_PER_CALL)
                ->each(function ($platformOrderIds) use ($tiktokUser) {
                    $response = $tiktokUser->getOrder($platformOrderIds->implode(','));

                    if (Arr::get($response, 'error')) {
                        Sentry::captureMessage('TikTok fulfilment order sync refused: '.json_encode(Arr::get($response, 'data')));

                        return;
                    }

                    foreach (Arr::get($response, 'data.orders', []) as $order) {
                        $this->handle($tiktokUser, $order);
                    }
                });
        }

        return 0;
    }
}
