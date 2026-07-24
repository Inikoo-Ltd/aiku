<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\PriceChangeNotification;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use Illuminate\Support\Carbon;
use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPriceChangePerOutbox
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
        $last24Hours = Carbon::now()->utc()->subHours(24);

        $lastOutBoxSent = $outbox->last_sent_at;

        $productClass = class_basename(Product::class);

        //  Check another condition:
        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');

        // check customer comms
        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_price_change_notification', true);
        });

        // check portfolio
        $baseQuery->join('portfolios', function ($join) use ($productClass) {
            $join->on('customers.id', '=', 'portfolios.customer_id')
                ->where('portfolios.item_type', $productClass)
                ->where('portfolios.status', true);
        });

        $baseQuery->join('customer_sales_channels', function ($join) {
            $join->on('portfolios.customer_sales_channel_id', '=', 'customer_sales_channels.id')
                ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN)
                ->where('customer_sales_channels.platform_status', true);
        });

        // check product (rebels that do not follow master pricing)
        $baseQuery->join('products', function ($join) {
            $join->on('portfolios.item_id', '=', 'products.id');
            $join->where('products.is_for_sale', true);
            $join->where('products.not_follow_master_prices', true);
            $join->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value,
            ]);
            $join->whereNull('products.deleted_at');
            $join->whereNotNull('products.master_product_id');
        });

        // check master asset price/rrp change from the audit trail
        $baseQuery->join('audits', function ($join) use ($last24Hours, $lastOutBoxSent) {
            $join->on('audits.auditable_id', '=', 'products.master_product_id')
                ->where('audits.auditable_type', 'MasterAsset')
                ->where('audits.event', 'updated')
                ->where('audits.created_at', '>', $last24Hours)
                ->whereRaw("(jsonb_exists(audits.new_values, 'price') OR jsonb_exists(audits.new_values, 'rrp'))");

            if ($lastOutBoxSent) {
                $join->where('audits.created_at', '>', $lastOutBoxSent);
            }
        });

        $baseQuery->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(DISTINCT products.id::TEXT, \',\' ORDER BY products.id::TEXT) AS product_ids')
        );
        $baseQuery->groupBy('customers.id');

        $baseQuery->orderBy('customers.id');
        ;

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            // create email bulk run
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chuckSize = 50;
        $baseQuery->chunk($chuckSize, function ($customers) use ($emailBulkRun) {
            $customerData = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($customer) => [
                    'id'          => $customer->id,
                    'product_ids' => $customer->product_ids,
                ])
                ->values()
                ->all();

            ProcessPriceChangeRecipients::dispatch(
                $emailBulkRun->id,
                $customerData
            );
            $this->countRecipients += count($customerData);
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
