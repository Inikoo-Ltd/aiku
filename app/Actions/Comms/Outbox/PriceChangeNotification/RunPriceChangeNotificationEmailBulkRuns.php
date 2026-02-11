<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 10 Feb 2026 14:35:27 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\PriceChangeNotification;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RunPriceChangeNotificationEmailBulkRuns
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;

    public string $commandSignature = 'run:price-change-notification';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        // $queryOutbox->whereIn('id', [1114]); //test for dropshipping UK
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();
        $last24Hours = now()->subHours(24);

        /**
         * check following steps :
         * 1. check channel still active or not
         * 2. check product still active or not
         * 3. check the product price change
         * 4. check the product is_for_sale
         * 5. check the product state
         * 6. check table historic asset to check the product price change
         * 7. optimize the query
         * 8. make sure unsubscribe is working
         *
         */
        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            $shop = $outbox->shop;
            if (!$shop->is_aiku) {
                continue;
            }

            $emailongoingRun = $outbox->emailOngoingRun;

            $lastOutBoxSent = $outbox->last_sent_at;

            $productClass = class_basename(Product::class);

            //  Check another condition:
            $baseQuery = QueryBuilder::for(Customer::class);
            $baseQuery->where('customers.shop_id', $outbox->shop_id);

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
                    ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN);
            });

            // check product
            $baseQuery->join('products', function ($join) use ($last24Hours) {
                $join->on('portfolios.item_id', '=', 'products.id');
                $join->where('products.is_for_sale', true);
                $join->where('products.price_updated_at', '>', $last24Hours);
                $join->whereNull('products.deleted_at');
            });

            $baseQuery->select(
                'customers.id',
                'customers.email',
                DB::raw('STRING_AGG(products.id::TEXT, \',\' ORDER BY products.id) AS product_ids')
            );
            $baseQuery->groupBy('customers.id');

            $baseQuery->orderBy('customers.id');
            //  Log the query
            // \Log::info($baseQuery->toRawSql());


            $lastBulkRun = null;
            $updateLastOutBoxSent = null;

            // create email bulk run
            $emailBulkRun = StoreEmailBulkRun::make()->action($emailongoingRun, [
                'scheduled_at' => now(),
                'subject'      => now()->format('Y.m.d'),
                'state'        => EmailBulkRunStateEnum::SCHEDULED,
            ], 0, false);


            $baseQuery->chunk(250, function ($customers) use ($emailBulkRun, $outbox) {
                $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun);

                foreach ($customers as $customer) {
                    if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                        StoreDispatchedEmail::run(
                            $emailBulkRun,
                            $customer,
                            [
                                'is_test'       => false,
                                'outbox_id'     => $outbox->id,
                                'email_address' => $customer->email,
                                'provider'      => DispatchedEmailProviderEnum::SES,
                            ]
                        );
                    }
                }

                // After processing the chunk, update and dispatch the delivery channel
                UpdateEmailDeliveryChannel::run(
                    $emailDeliveryChannel,
                    [
                        'number_emails' => $emailBulkRun->dispatchedEmails()->where('channel', $emailDeliveryChannel->id)->count()
                    ]
                );
                SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
            });

            // foreach ($baseQuery-> as $customer) {
            //     $processedCount++;

            //     if ($lastCustomer === null) {
            //         $lastCustomer = $customer;
            //     }

            //     // running code for sending email
            //     if ($lastCustomer->id !== $customer->id) {
            //         $bulkRun = $this->upsertEmailBulkRuns($lastCustomer, $outbox->code, $currentDateTime->toDateTimeString());
            //         $additionalData = [
            //             'products' => $this->generateProductLinks($productData),
            //         ];
            //         // SendBackToStockToCustomerEmail::dispatch($lastCustomer, $outbox->code, $additionalData, $bulkRun);

            //         $lastBulkRun = $bulkRun;

            //         $lastCustomer = $customer;
            //         $productData = []; // Reset for new customer

            //         // Update last sent time for this outbox
            //         $updateLastOutBoxSent = $currentDateTime;
            //     }

            //     $productData[] = [
            //         'product_id' => $customer->product_id,
            //     ];

            //     if ($processedCount === $totalCustomers) {
            //         // Process the last batch
            //         $bulkRun = $this->upsertEmailBulkRuns($lastCustomer, $outbox->code, $currentDateTime->toDateTimeString());
            //         $additionalData = [
            //             'products' => $this->generateProductLinks($productData),
            //         ];
            //         // SendBackToStockToCustomerEmail::dispatch($lastCustomer, $outbox->code, $additionalData, $bulkRun);
            //         // reset product data
            //         $productData = [];
            //         $lastBulkRun = $bulkRun;

            //         // Update last sent time for this outbox
            //         $updateLastOutBoxSent = $currentDateTime;
            //     }
            // }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }

    public function generateProductLinks(array $productData): string
    {
        $date = Carbon::now()->format('d M y');

        $html = '';

        $html .= '<table width="100%" cellpadding="8" cellspacing="0"
        style="font-family: Helvetica, Arial, sans-serif;
               font-size: 14px;
               border-collapse: collapse;">';


        $html .= '
        <tr style="border-bottom:1px solid #e5e7eb;">
            <th align="left" style="color:#555;">' . __('Product') . '</th>
            <th align="center" style="color:#555;">' . __('New stock') . ' (' . $date . ')</th>
        </tr>';

        foreach ($productData as $product) {
            $dataProduct = Product::find($product['product_id']);

            if (!$dataProduct) {
                continue;
            }

            $productImage = Arr::get(
                $dataProduct->imageSources(200, 200),
                'original'
            );

            $stock = $dataProduct->available_quantity ?? 0;


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
                    . $stock .
                    '</td>
                </tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }
}
