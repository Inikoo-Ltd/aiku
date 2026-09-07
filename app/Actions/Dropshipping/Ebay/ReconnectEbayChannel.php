<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 12:40:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReconnectEbayChannel
{
    use AsAction;

    /**
     * Runs right after eBay hands back a token. Records which eBay account the token belongs to and, when this
     * customer already has a channel for that account, hands that channel to the freshly authorised row instead
     * of leaving a second channel behind.
     */
    public function handle(EbayUser $ebayUser): CustomerSalesChannel
    {
        $identity = self::storeIdentity($ebayUser);
        $userId   = Arr::get($identity, 'userId');

        if (!$userId) {
            return $ebayUser->customerSalesChannel;
        }

        $existing = self::otherRowForSameAccount($ebayUser, $userId);

        if (!$existing) {
            return $ebayUser->customerSalesChannel;
        }

        return $this->takeOverChannel($ebayUser, $existing);
    }

    /**
     * @return array{userId: string|null, username: string|null}
     */
    public static function storeIdentity(EbayUser $ebayUser): array
    {
        $identity = $ebayUser->getUser();
        $userId   = Arr::get($identity, 'userId');

        if (!$userId) {
            return ['userId' => null, 'username' => null];
        }

        $stored = ['userId' => (string) $userId, 'username' => Arr::get($identity, 'username')];

        UpdateEbayUser::run($ebayUser, ['data' => ['ebay_user' => $stored]]);

        return $stored;
    }

    public static function otherRowForSameAccount(EbayUser $ebayUser, string $userId): ?EbayUser
    {
        return EbayUser::where('customer_id', $ebayUser->customer_id)
            ->whereKeyNot($ebayUser->id)
            ->where('data->ebay_user->userId', $userId)
            ->whereHas('customerSalesChannel')
            ->get()
            ->sortBy([
                fn (EbayUser $a, EbayUser $b) => ($b->customerSalesChannel->status == CustomerSalesChannelStatusEnum::OPEN) <=> ($a->customerSalesChannel->status == CustomerSalesChannelStatusEnum::OPEN),
                fn (EbayUser $a, EbayUser $b) => $b->customerSalesChannel->number_portfolios <=> $a->customerSalesChannel->number_portfolios,
                fn (EbayUser $a, EbayUser $b) => $b->id <=> $a->id,
            ])
            ->first();
    }

    private function takeOverChannel(EbayUser $fresh, EbayUser $stale): CustomerSalesChannel
    {
        $keep   = $stale->customerSalesChannel;
        $minted = $fresh->customerSalesChannel;

        $wasClosed = $keep->status == CustomerSalesChannelStatusEnum::CLOSED;

        DB::transaction(function () use ($fresh, $stale, $keep, $minted) {
            $fresh->update([
                'customer_sales_channel_id' => $keep->id,
                'marketplace'               => $fresh->marketplace ?? $stale->marketplace,
                'fulfillment_policy_id'     => $fresh->fulfillment_policy_id ?? $stale->fulfillment_policy_id,
                'payment_policy_id'         => $fresh->payment_policy_id ?? $stale->payment_policy_id,
                'return_policy_id'          => $fresh->return_policy_id ?? $stale->return_policy_id,
                'location_key'              => $fresh->location_key ?? $stale->location_key,
            ]);

            $stale->update(['customer_sales_channel_id' => $minted->id]);

            UpdateCustomerSalesChannel::run($keep, [
                'platform_user_type' => class_basename($fresh),
                'platform_user_id'   => $fresh->id,
                'status'             => CustomerSalesChannelStatusEnum::OPEN,
                'name'               => preg_replace('/ - deleted - \d+$/', '', (string) $keep->name) ?: $fresh->name,
                'closed_at'          => null,
            ]);

            UpdateCustomerSalesChannel::run($minted, [
                'platform_user_type' => class_basename($stale),
                'platform_user_id'   => $stale->id,
            ]);
        });

        if ($wasClosed) {
            foreach ($keep->portfolios as $portfolio) {
                UpdatePortfolio::run($portfolio, ['status' => true]);
            }
        }

        MergeEbayChannelInto::run($minted->refresh(), $keep->refresh());

        return $keep->refresh();
    }
}
