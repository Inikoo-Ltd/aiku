<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Mon, 21 Sept 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\FavouritesOnOffer;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessFavouritesOnOfferPerOutbox
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

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');

        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_favourites_on_offer', true);
        });

        $baseQuery->join('favourites', function ($join) {
            $join->on('customers.id', '=', 'favourites.customer_id')
                ->whereNull('favourites.unfavourited_at');
        });

        $baseQuery->join('products', function ($join) use ($since) {
            $join->on('favourites.product_id', '=', 'products.id');
            $join->where('products.is_for_sale', true);
            $join->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value,
            ]);
            $join->whereNull('products.deleted_at');
            $join->where(function ($query) use ($since) {
                $query->where('products.price_updated_at', '>', $since)
                    ->orWhere('products.updated_at', '>', $since);
            });
        });

        $baseQuery->where(function ($query) use ($since) {
            $query->where(function ($priceDrop) use ($since) {
                $priceDrop->where('products.price_updated_at', '>', $since)
                    ->whereRaw($this->previousPriceSubQuery().' > products.price');
            })->orWhere(function ($newOffer) use ($since) {
                $newOffer->whereRaw("(products.offers_data::jsonb->'best_percentage_off'->>'percentage_off')::numeric > 0")
                    ->whereExists(function ($offerQuery) use ($since) {
                        $offerQuery->selectRaw('1')
                            ->from('offers')
                            ->whereRaw("offers.id = (products.offers_data::jsonb->'best_percentage_off'->>'offer_id')::bigint")
                            ->where('offers.start_at', '>', $since);
                    });
            });
        });

        $baseQuery->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(products.id::TEXT, \',\' ORDER BY products.id) AS product_ids')
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

            ProcessFavouritesOnOfferRecipients::dispatch(
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
