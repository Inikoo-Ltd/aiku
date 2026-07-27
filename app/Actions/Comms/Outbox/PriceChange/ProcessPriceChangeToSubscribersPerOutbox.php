<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\PriceChange;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use App\Enums\Catalogue\Product\ProductStateEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPriceChangeToSubscribersPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;
    protected int $countRecipients = 0;
    public string $jobQueue = 'ses';

    public function handle(Outbox $outbox): void
    {
        $shop = $outbox->shop;
        if (!$shop->is_aiku) {
            return;
        }

        $interval = $outbox->interval ?? 0;

        $currentDateTime = Carbon::now()->utc();
        $lastCheckInMinuties = Carbon::now()->utc()->subMinutes($interval);

        $lastOutBoxSent = $outbox->last_sent_at;

        // products of the shop (rebels that do not follow master pricing) whose master asset
        // price/rrp changed within the window, from the audit trail
        $baseQuery = DB::table('products');
        $baseQuery->where('products.shop_id', $outbox->shop_id);
        $baseQuery->where('products.is_for_sale', true);
        $baseQuery->where('products.not_follow_master_prices', true);
        $baseQuery->whereIn('products.state', [
            ProductStateEnum::ACTIVE->value,
            ProductStateEnum::DISCONTINUING->value,
        ]);
        $baseQuery->whereNull('products.deleted_at');
        $baseQuery->whereNotNull('products.master_product_id');

        $baseQuery->join('audits', function ($join) use ($lastCheckInMinuties, $lastOutBoxSent) {
            $join->on('audits.auditable_id', '=', 'products.master_product_id')
                ->where('audits.auditable_type', 'MasterAsset')
                ->where('audits.event', 'updated')
                ->where('audits.created_at', '>', $lastCheckInMinuties)
                // ponytail: only scalar price/rrp keys, per-currency-only changes written to
                // master_prices/master_rrps jsonb won't trigger. Widen the jsonb_exists if that matters.
                ->whereRaw("(jsonb_exists(audits.new_values, 'price') OR jsonb_exists(audits.new_values, 'rrp'))");

            if ($lastOutBoxSent) {
                $join->where('audits.created_at', '>', $lastOutBoxSent);
            }
        });

        $baseQuery->select('products.id', 'products.master_product_id');
        $baseQuery->distinct();
        $baseQuery->orderBy('products.master_product_id');
        $baseQuery->orderBy('products.id');

        $rows = $baseQuery->get();

        if ($rows->isEmpty()) {
            return;
        }

        $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());

        $groupedProductIds = $rows
            ->groupBy('master_product_id')
            ->map(fn ($group) => $group->pluck('id')->all())
            ->toJson();

        $chuckSize = 50;
        $outbox->subscribedUsers()->chunkById($chuckSize, function ($subscribers) use ($emailBulkRun, $groupedProductIds) {
            $subscriberIds = $subscribers->pluck('id')->all();

            ProcessPriceChangeToSubscribersRecipients::dispatch(
                $emailBulkRun->id,
                $subscriberIds,
                $groupedProductIds
            );
            $this->countRecipients += count($subscriberIds);
        });

        $emailBulkRun->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients,
        ]);

        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        $outbox->update([
            'last_sent_at' => $currentDateTime
        ]);
    }
}
