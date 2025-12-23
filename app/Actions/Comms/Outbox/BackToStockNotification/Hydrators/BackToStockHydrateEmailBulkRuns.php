<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackToStockNotification\Hydrators;

use App\Actions\Comms\Email\SendBackToStockToCustomerEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Comms\Outbox\BackToStockNotification\BulkDeleteBackInStockReminder;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\Outbox;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackToStockHydrateEmailBulkRuns implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;
    public string $commandSignature = 'hydrate:back-in-stock-reminder';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::OOS_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        // $queryOutbox->whereIn('id', [826]); //test for bulgaria
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        foreach ($outboxes as $outbox) {
            $lastOutBoxSent = $outbox->last_sent_at;
            // make customers as main table
            $baseQuery = QueryBuilder::for(Customer::class);
            $baseQuery->join('back_in_stock_reminders', 'customers.id', '=', 'back_in_stock_reminders.customer_id');
            $baseQuery->join('products', 'back_in_stock_reminders.product_id', '=', 'products.id');
            // select options
            $baseQuery->select('customers.id', 'customers.shop_id', 'back_in_stock_reminders.product_id as product_id', 'products.name as product_name', 'back_in_stock_reminders.id as reminder_id');

            // where conditions
            $baseQuery->where('back_in_stock_reminders.shop_id', $outbox->shop_id);
            $baseQuery->where('products.available_quantity', '>', 0);
            $baseQuery->where('products.back_in_stock_since', '>', DB::raw('back_in_stock_reminders.created_at'));
            if ($lastOutBoxSent) {
                $baseQuery->where('back_in_stock_reminders.created_at', '>', $lastOutBoxSent);
                $baseQuery->where('products.back_in_stock_since', '>', $lastOutBoxSent);
            }
            // check another customers conditions
            $baseQuery->whereNull('customers.deleted_at');
            // check another product conditions
            $baseQuery->whereNull('products.deleted_at');
            // order by customer id
            $baseQuery->orderBy('customers.id');

            $LastBulkRun = null;
            $updateLastOutBoxSent = null;

            // Get count before iterating
            $totalCustomers = (clone $baseQuery)->count() ?? 0;

            $processedCount = 0;
            $productData = [];
            $lastCustomerId = null;
            $deleteBackInStockReminderIds = [];
            foreach ($baseQuery->cursor() as $customer) {
                $processedCount++;

                if ($lastCustomerId === null) {
                    $lastCustomerId = $customer->id;
                }


                // running code for sending email
                if ($lastCustomerId !== $customer->id) {
                    $bulkRun = $this->generateEmailBulkRuns($customer, $outbox->code, $currentDateTime->toDateTimeString());
                    $additionalData = [
                        'products' => $this->generateProductLinks($productData),
                    ];
                    SendBackToStockToCustomerEmail::dispatch($customer, $outbox->code, $additionalData, $bulkRun);

                    $LastBulkRun = $bulkRun;

                    $lastCustomerId = $customer->id;
                    $productData = []; // Reset for new customer

                    // Update last sent time for this outbox
                    $updateLastOutBoxSent = $currentDateTime;
                }

                // get the product canonical url
                $product = Product::find($customer->product_id);
                $canonicalUrl = null;
                if ($product) {
                    $webPage = $product->webpage ?? null;
                    if ($webPage) {
                        $canonicalUrl = $webPage->getCanonicalUrl();
                    }
                }

                $productData[] = [
                    'product_id' => $customer->product_id,
                    'product_name' => $customer->product_name,
                    'canonical_url' => $canonicalUrl,
                ];

                // $updateLastOutBoxSent = $currentDateTime;
                if ($processedCount === $totalCustomers) {

                    // Process the last batch
                    $bulkRun = $this->generateEmailBulkRuns($customer, $outbox->code, $currentDateTime->toDateTimeString());
                    $additionalData = [
                        'products' => $this->generateProductLinks($productData),
                    ];
                    SendBackToStockToCustomerEmail::dispatch($customer, $outbox->code, $additionalData, $bulkRun);
                    // reset product data
                    $productData = [];
                    $LastBulkRun = $bulkRun;

                    // Update last sent time for this outbox
                    $updateLastOutBoxSent = $currentDateTime;
                }

                // Track reminder IDs to delete
                $deleteBackInStockReminderIds[] = $customer->reminder_id;
            }

            // Note: Make sure this runs only once at the end
            // check Job Chaining Bus::chain
            if ($LastBulkRun) {
                EmailBulkRunHydrateDispatchedEmails::dispatch($LastBulkRun);
            }

            if ($updateLastOutBoxSent) {
                // update last_sent_at for this outbox
                $this->update($outbox, ['last_sent_at' => $updateLastOutBoxSent]);
            }

            // Delete processed back_in_stock_reminders
            if (!empty($deleteBackInStockReminderIds)) {
                BulkDeleteBackInStockReminder::run($deleteBackInStockReminderIds);
                // reset array to avoid re-deleting the same IDs
                $deleteBackInStockReminderIds = [];
            }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }

    public function generateProductLinks(array $productData): string
    {
        $links = [];

        foreach ($productData as $product) {
            $url = $product['canonical_url'];
            $name = $product['product_name'];

            $links[] = "<a ses:no-track href=\"{$url}\">{$name}</a>";
        }

        return implode(', ', $links);
    }
}
