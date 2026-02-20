<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 18 Feb 2026 14:49:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRun;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RunBasketOfferNotificationBulkRuns
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;

    public string $commandSignature = 'run:basket-offer-notification';
    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['basket_offer_notification'];
    }

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::BASKET_OFFER_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            $shop = $outbox->shop;
            if (!$shop->is_aiku) {
                continue;
            }

            $currentDateTime = Carbon::now()->utc();
            $intervalInHours = Carbon::now()->utc()->subHours($outbox->interval);
            $emailongoingRun = $outbox->emailOngoingRun;

            $lastOutBoxSent = $outbox->last_sent_at;

            $productClass = class_basename(Product::class);

            // Base query for customers - leave empty as requested
            $baseQuery = QueryBuilder::for(Customer::class);
            $baseQuery->where('customers.shop_id', $outbox->shop_id);
            $baseQuery->whereNull('customers.deleted_at');

            // Join Order
            $baseQuery->join('orders', function ($join) {
                $join->on('customers.id', '=', 'orders.customer_id');
                $join->where('orders.state', OrderStateEnum::CREATING);
                $join->where('orders.status', OrderStatusEnum::CREATING);
                $join->whereNull('orders.deleted_at');
            });

            // Join Transactions
            $baseQuery->join('transactions', function ($join) {
                $join->on('orders.id', '=', 'transactions.order_id');
            });

            // Join Products
            $baseQuery->join('products', function ($join) use ($productClass) {
                $join->on('transactions.model_id', '=', 'products.id');
                $join->where('transactions.model_type', $productClass);
                $join->where('products.is_for_sale', true);
                $join->whereIn('products.state', [
                    ProductStateEnum::ACTIVE,
                    ProductStateEnum::DISCONTINUING,
                ]);
                $join->whereNull('products.deleted_at');
            });

            // Filter by price history
            // Innermost: rank historic assets per product
            $rankedQuery = DB::table('historic_assets')
                ->select('model_id', 'price')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY model_id ORDER BY id DESC) AS rn')
                ->where('model_type', $productClass);

            // Middle: aggregate latest and second latest price per product
            $priceComparisonQuery = DB::table(DB::raw("({$rankedQuery->toSql()}) AS ranked"))
                ->mergeBindings($rankedQuery)
                ->select('model_id')
                ->selectRaw('MAX(CASE WHEN rn = 1 THEN price END) AS latest_price')
                ->selectRaw('MAX(CASE WHEN rn = 2 THEN price END) AS second_latest_price')
                ->groupBy('model_id');

            // Outer: filter only price dropped products
            $priceDroppedProductsQuery = DB::table(DB::raw("({$priceComparisonQuery->toSql()}) AS price_comparison"))
                ->mergeBindings($priceComparisonQuery)
                ->select('model_id')
                ->whereRaw('latest_price < second_latest_price');

            // Join price dropped products
            $baseQuery->joinSub($priceDroppedProductsQuery, 'price_dropped_products', function ($join) {
                $join->on('products.id', '=', 'price_dropped_products.model_id');
            });

            // Left join ranked historic assets (last 2 per product)
            $rankedHistoricAssetsQuery = DB::table('historic_assets')
                ->select('id', 'model_id')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY model_id ORDER BY id DESC) AS rn')
                ->where('model_type', $productClass);

            $baseQuery->leftJoinSub($rankedHistoricAssetsQuery, 'ranked_historic_assets', function ($join) {
                $join->on('products.id', '=', 'ranked_historic_assets.model_id')
                    ->where('ranked_historic_assets.rn', '<=', 2);
            });

            // Filter by discount offer - join with products to get their associated offers
            $productOffersQuery = DB::table(DB::raw('(
                SELECT
                    product_id,
                    STRING_AGG(offer_id::TEXT, \',\' ORDER BY offer_id) AS offer_ids
                FROM (
                    -- Product → Product trigger
                    SELECT
                        p.id AS product_id,
                        o.id AS offer_id
                    FROM products AS p
                    JOIN offers AS o
                        ON p.id = o.trigger_id 
                       AND o.trigger_type = \'Product\'

                    UNION

                    -- Product → ProductCategory (family)
                    SELECT
                        p.id AS product_id,
                        o.id AS offer_id
                    FROM products AS p
                    JOIN offers AS o
                        ON p.family_id = o.trigger_id 
                       AND o.trigger_type = \'ProductCategory\'

                    UNION

                    -- Product → ProductCategory (sub_department)
                    SELECT
                        p.id AS product_id,
                        o.id AS offer_id
                    FROM products AS p
                    JOIN offers AS o
                        ON p.sub_department_id = o.trigger_id 
                       AND o.trigger_type = \'ProductCategory\'

                    UNION

                    -- Product → ProductCategory (department)
                    SELECT
                        p.id AS product_id,
                        o.id AS offer_id
                    FROM products AS p
                    JOIN offers AS o
                        ON p.department_id = o.trigger_id 
                       AND o.trigger_type = \'ProductCategory\'
                )
                GROUP BY product_id
                ORDER BY product_id
            ) AS product_offers'));

            $baseQuery->joinSub($productOffersQuery, 'product_offers', function ($join) {
                $join->on('products.id', '=', 'product_offers.product_id');
            });


            $baseQuery->select(
                'customers.id',
                'customers.email',
                DB::raw('STRING_AGG(DISTINCT products.id::TEXT, \',\' ORDER BY products.id::TEXT) AS product_ids'),
                DB::raw('STRING_AGG(ranked_historic_assets.id::TEXT, \',\' ORDER BY ranked_historic_assets.id::TEXT) AS historic_asset_ids'),
                DB::raw('STRING_AGG(DISTINCT product_offers.offer_id::TEXT, \',\' ORDER BY product_offers.offer_id::TEXT) AS offer_ids'),
            );
            $baseQuery->groupBy('customers.id');
            $baseQuery->orderBy('customers.id');

            // Log the query
            Log::info($baseQuery->toRawSql());

            // create email bulk run
            // $emailBulkRun = StoreEmailBulkRun::make()->action($emailongoingRun, [
            //     'scheduled_at' => now(),
            //     'subject'      => now()->format('Y.m.d'),
            //     'state'        => EmailBulkRunStateEnum::SCHEDULED,
            // ], 0, false);

            // $baseQuery->chunk(250, function ($customers) use ($emailBulkRun, $outbox) {

            //     $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun);

            //     foreach ($customers as $customer) {

            //         if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {

            //             $dispatchedEmail = StoreDispatchedEmail::run(
            //                 $emailBulkRun,
            //                 $customer,
            //                 [
            //                     'is_test'       => false,
            //                     'outbox_id'     => $outbox->id,
            //                     'email_address' => $customer->email,
            //                     'provider'      => DispatchedEmailProviderEnum::SES,
            //                     'data->additional_data' => [
            //                         'products' => $this->generateProductLinks($customer->product_ids)
            //                     ]
            //                 ]
            //             );

            //             StoreEmailBulkRunRecipient::run(
            //                 $emailBulkRun,
            //                 [
            //                     'dispatched_email_id' => $dispatchedEmail->id,
            //                     'recipient_type'      => class_basename($customer),
            //                     'recipient_id'        => $customer->id,
            //                     'channel'             => $emailDeliveryChannel->id,
            //                 ]
            //             );
            //         }
            //     }

            //     // After processing the chunk, update and dispatch the delivery channel
            //     UpdateEmailDeliveryChannel::run(
            //         $emailDeliveryChannel,
            //         [
            //             'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count()
            //         ]
            //     );
            //     SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
            // });

            // UpdateEmailBulkRun::run(
            //     $emailBulkRun,
            //     [
            //         'recipients_stored_at' => now()
            //     ]
            // );

            // // update last outbox sent
            // $this->update($outbox, ['last_sent_at' => $currentDateTime]);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }

    public function generateProductLinks(string $productIds): string
    {
        try {
            $productIds = explode(',', $productIds);
        } catch (\Throwable $th) {
            Log::error('Error parsing product IDs: ' . $th->getMessage());
            return '';
        }

        $date = Carbon::now()->format('d M y');

        $html = '';

        $html .= '<table width="100%" cellpadding="8" cellspacing="0"
        style="font-family: Helvetica, Arial, sans-serif;
               font-size: 14px;
               border-collapse: collapse;">';


        $html .= '
        <tr style="border-bottom:1px solid #e5e7eb;">
            <th align="left" style="color:#555;">' . __('Product') . '</th>
            <th align="center" style="color:#555;">' . __('New price') . ' (' . $date . ')</th>
        </tr>';

        foreach ($productIds as $productId) {
            $dataProduct = Product::find($productId);

            if (!$dataProduct) {
                continue;
            }

            $productImage = Arr::get(
                $dataProduct->imageSources(200, 200),
                'original'
            );

            $currency = $dataProduct->currency;
            $currencySymbol = $currency?->symbol ?? '$';
            $fractionDigit = $currency?->fraction_digit ?? 2;
            $formattedPrice = number_format($dataProduct->price ?? 0, $fractionDigit);
            $displayPrice = $currencySymbol . $formattedPrice;


            if ($dataProduct->webpage) {
                $url  = $dataProduct->webpage->getCanonicalUrl();
                $name = $dataProduct->name;

                $html .= '
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="vertical-align:middle;">
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding-right:12px;">';

                if ($productImage) {
                    $html .= '
                    <img src="' . $productImage . '"
                         width="60"
                         height="60"
                         style="display:block;
                                border-radius:6px;
                                object-fit:cover;" />';
                }

                $html .= '
                                </td>
                                <td style="vertical-align:middle;">
                                    <a ses:no-track href="' . $url . '"
                                       style="color:#2563eb;
                                        text-decoration:underline;
                                        font-weight:600;">'
                    . $name .
                    '</a>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td align="center"
                        style="font-weight:600;
                               color:#16a34a;">'
                    . $displayPrice .
                    '</td>
                </tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }
}
