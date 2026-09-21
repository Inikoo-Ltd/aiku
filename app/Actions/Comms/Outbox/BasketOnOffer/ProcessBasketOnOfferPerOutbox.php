<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Mon, 21 Sept 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\BasketOnOffer;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessBasketOnOfferPerOutbox
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
        $since           = $outbox->last_sent_at ?? $currentDateTime->copy()->subHours(24);

        $productClass = class_basename(Product::class);

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');

        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_basket_on_offer', true);
        });

        $baseQuery->join('orders', function ($join) {
            $join->on('customers.id', '=', 'orders.customer_id');
            $join->where('orders.state', OrderStateEnum::CREATING->value);
            $join->where('orders.status', OrderStatusEnum::CREATING->value);
            $join->whereNull('orders.deleted_at');
        });

        $baseQuery->join('transactions', function ($join) use ($productClass) {
            $join->on('orders.id', '=', 'transactions.order_id');
            $join->where('transactions.model_type', $productClass);
            $join->whereNull('transactions.deleted_at');
        });

        $baseQuery->join('products', function ($join) {
            $join->on('transactions.model_id', '=', 'products.id');
            $join->where('products.is_for_sale', true);
            $join->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value,
            ]);
            $join->whereNull('products.deleted_at');
        });

        $baseQuery->where(function ($query) use ($since) {
            $query->where(function ($priceDrop) use ($since) {
                $priceDrop->where('products.price_updated_at', '>', $since)
                    ->whereRaw($this->previousPriceSubQuery().' > products.price');
            })->orWhereRaw("(products.offers_data::jsonb->'best_percentage_off'->>'percentage_off')::numeric > 0");
        });

        $baseQuery->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(DISTINCT products.id::TEXT, \',\') AS product_ids')
        );
        $baseQuery->groupBy('customers.id');
        $baseQuery->orderBy('customers.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chunkSize = 50;
        $baseQuery->chunk($chunkSize, function ($customers) use ($emailBulkRun) {
            $customerData = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($customer) => [
                    'id'          => $customer->id,
                    'product_ids' => $customer->product_ids,
                ])
                ->values()
                ->all();

            ProcessBasketOnOfferRecipients::dispatch(
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

    protected function previousPriceSubQuery(): string
    {
        return '(select ha.price from historic_assets ha
            where ha.asset_id = products.asset_id
              and ha.id < products.current_historic_asset_id
            order by ha.id desc limit 1)';
    }
}
