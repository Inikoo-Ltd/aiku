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

        $currentDateTime = Carbon::now()->utc();

        $lastOutBoxSent = $outbox->last_sent_at ??  null;
        $interval = $outbox->interval ?? 10; // by default 10 minutes

        // Check if enough time has passed since last outbox was sent
        if ($lastOutBoxSent && Carbon::parse($lastOutBoxSent)->diffInMinutes($currentDateTime) < $interval) {
            return;
        }

        $lastOutBoxSent = $lastOutBoxSent ?? $currentDateTime->copy()->subHours(24);


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

        $baseQuery->join('audits', function ($join) use ($lastOutBoxSent) {
            $join->on('audits.auditable_id', '=', 'products.master_product_id')
                ->where('audits.auditable_type', 'MasterAsset')
                ->where('audits.event', 'updated')
                ->where('audits.created_at', '>', $lastOutBoxSent)
                ->whereRaw("(jsonb_exists(audits.new_values, 'price') OR jsonb_exists(audits.new_values, 'rrp'))");
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
