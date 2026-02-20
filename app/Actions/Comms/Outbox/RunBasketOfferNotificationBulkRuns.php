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
use App\Models\Catalogue\ProductCategory;
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
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at', 'outboxes.interval');
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
            $productCategoryClass = class_basename(ProductCategory::class);

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

            // Build product offers subquery using json_build_object + json_agg
            $productOffersQuery = DB::table('offers AS o')
                ->select(
                    'p.id AS product_id',
                    DB::raw('json_build_object(p."id"::text, json_agg(DISTINCT o."id"::text)) AS product_offer_map')
                )
                ->join('products AS p', function ($join) use ($outbox, $intervalInHours, $productCategoryClass, $productClass) {
                    $join->on('o.shop_id', '=', DB::raw($outbox->shop_id))
                        ->whereNull('o.deleted_at')
                        // ->where('o.created_at', '>=', $intervalInHours)
                        ->where(function ($query) use ($productCategoryClass, $productClass) {
                            $query->where(function ($q) use ($productClass) {
                                $q->where('o.trigger_type', '=', $productClass)
                                    ->whereColumn('p.id', 'o.trigger_id');
                            })->orWhere(function ($q) use ($productCategoryClass) {
                                $q->where('o.trigger_type', '=', $productCategoryClass)
                                    ->whereColumn('p.family_id', 'o.trigger_id');
                            })->orWhere(function ($q) use ($productCategoryClass) {
                                $q->where('o.trigger_type', '=', $productCategoryClass)
                                    ->whereColumn('p.sub_department_id', 'o.trigger_id');
                            })->orWhere(function ($q) use ($productCategoryClass) {
                                $q->where('o.trigger_type', '=', $productCategoryClass)
                                    ->whereColumn('p.department_id', 'o.trigger_id');
                            });
                        });
                })
                ->groupBy('p.id')
                ->orderBy('p.id');

            // Left join the product offers subquery
            $baseQuery->leftJoinSub($productOffersQuery, 'product_offers', function ($join) {
                $join->on('products.id', '=', 'product_offers.product_id');
            });

            // Apply the price_drop_at OR product_offers condition (moved from join to where)
            $baseQuery->where(function ($query) use ($intervalInHours) {
                $query->where('products.price_drop_at', '>=', $intervalInHours)
                    ->orWhereNotNull('product_offers.product_id');
            });

            $baseQuery->select(
                'customers.id',
                'customers.email',
                DB::raw('STRING_AGG(DISTINCT products.id::TEXT, \',\' ORDER BY products.id::TEXT) AS product_ids'),
                DB::raw('STRING_AGG(DISTINCT "product_offers"."product_offer_map"::TEXT, \',\') AS offer_ids_map'),
            );
            $baseQuery->groupBy('customers.id');
            $baseQuery->orderBy('customers.id');
            // $baseQuery->limit(5);

            // Log the query
            Log::info($baseQuery->toRawSql());
            Log::info($baseQuery->get());

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

//  NOTE: Example SQL Query
// select
//   "customers"."id",
//   "customers"."email",
//   STRING_AGG(
//     DISTINCT products.id :: TEXT,
//     ','
//     ORDER BY
//       products.id :: TEXT
//   ) AS product_ids,
//     STRING_AGG(
//     DISTINCT "product_offers"."result" :: TEXT,
//     ','
//   ) AS list_offer_ids
// --  "product_offers"."result" as new_result
// from
//   "customers"
//   inner join "orders" on "customers"."id" = "orders"."customer_id"
//   and "orders"."state" = 'creating'
//   and "orders"."status" = 'creating'
//   and "orders"."deleted_at" is null
//   inner join "transactions" on "orders"."id" = "transactions"."order_id"
//   inner join "products" on "transactions"."model_id" = "products"."id"
//   and "transactions"."model_type" = 'Product'
//   and "products"."is_for_sale" = true
//   and "products"."state" in ('active', 'discontinuing')
//   and "products"."deleted_at" is null
//   left join (
//     select
//       p."id" as "product_id",
//       json_build_object(
//         p."id" :: text,
//         json_agg(DISTINCT o."id" :: text)
//       ) AS "result"
//     FROM
//       "offers" AS "o"
//       INNER JOIN "products" AS "p" ON "o"."shop_id" = 42
//       AND "o"."deleted_at" IS NULL
//       and "o"."created_at" >= '2026-02-16 03:02:18'
//       AND (
//         (
//           "o"."trigger_type" = 'Product'
//           AND "p"."id" = "o"."trigger_id"
//         )
//         OR (
//           "o"."trigger_type" = 'ProductCategory'
//           AND "p"."family_id" = "o"."trigger_id"
//         )
//         OR (
//           "o"."trigger_type" = 'ProductCategory'
//           AND "p"."sub_department_id" = "o"."trigger_id"
//         )
//         OR (
//           "o"."trigger_type" = 'ProductCategory'
//           AND "p"."department_id" = "o"."trigger_id"
//         )
//       )
//     GROUP BY
//       p."id"
//     order by
//       p."id" asc
//   ) as "product_offers" on "products"."id" = "product_offers"."product_id"
// where
//   "customers"."shop_id" = 42
//   and "customers"."deleted_at" is null
//   and "customers"."deleted_at" is null
//   and (
//     "products"."price_drop_at" >= '2026-02-16 03:02:18'
//     or "product_offers"."product_id" is not null
//   )
// group by
//   "customers"."id"
// order by
//   "customers"."id" asc
