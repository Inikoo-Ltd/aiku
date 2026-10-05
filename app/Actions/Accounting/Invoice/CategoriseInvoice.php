<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Mar 2025 19:07:16 Malaysia Time, Plane Chengdu - Kuala Lumpur
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoriseInvoice extends OrgAction
{
    use WithHydrateCommand;


    public function __construct()
    {
        $this->model = Invoice::class;
    }

    public function handle(Invoice $invoice): Invoice
    {
        $oldInvoiceCategory = $invoice->invoiceCategory;

        $invoiceCategory = $this->getInvoiceCategory($invoice);


        $invoice->update([
            'invoice_category_id' => $invoiceCategory?->id,
        ]);


        if ($invoice->wasChanged('invoice_category_id')) {
            if ($oldInvoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($oldInvoiceCategory)->delay($this->hydratorsDelay);
            }

            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            }
        }
        $invoice->refresh();

        return $invoice;
    }

    public function getInvoiceCategory(Invoice|Order $invoice, ?EloquentCollection $invoiceCategories = null): ?InvoiceCategory
    {
        $invoiceCategory = null;

        $invoiceCategories ??= $this->getActiveInvoiceCategories($invoice->organisation);
        /** @var InvoiceCategory $invoiceCategory */
        foreach ($invoiceCategories as $invoiceCategory) {
            $invoiceCategory = match ($invoiceCategory->type) {
                InvoiceCategoryTypeEnum::SHOP_TYPE => $this->inHaystack($invoiceCategory, 'shop_types', $invoice->shop->type->value),
                InvoiceCategoryTypeEnum::SHOP_FALLBACK => $this->shopFallback($invoice, $invoiceCategory),
                InvoiceCategoryTypeEnum::IN_COUNTRY => $this->inHaystack($invoiceCategory, 'country_ids', $invoice->billing_country_id),
                InvoiceCategoryTypeEnum::NOT_IN_COUNTRY => $this->notInHaystack($invoiceCategory, 'country_ids', $invoice->billing_country_id),
                InvoiceCategoryTypeEnum::IN_ORGANISATION => $this->inOrganisation($invoiceCategory, $invoice->as_organisation_id),
                InvoiceCategoryTypeEnum::VIP => $invoice->is_vip ? $invoiceCategory : null,
                InvoiceCategoryTypeEnum::EXTERNAL_INVOICER => $invoice instanceof Invoice && $invoice->external_invoicer_id ? $invoiceCategory : null,
                InvoiceCategoryTypeEnum::IN_SALES_CHANNEL => $this->inHaystack($invoiceCategory, 'sales_channel_ids', $invoice->sales_channel_id),
                InvoiceCategoryTypeEnum::IN_SALES_CHANNEL_SHOP => $this->salesChannelShop($invoice, $invoiceCategory),
                InvoiceCategoryTypeEnum::IN_SHOP_OR_IN_SALES_CHANNEL_SHOP => $this->inShopOrSalesChannelShop($invoice, $invoiceCategory),
            };


            if ($invoiceCategory) {
                break;
            }
        }

        return $invoiceCategory;
    }

    public function getActiveInvoiceCategories(Organisation $organisation): EloquentCollection
    {
        return $organisation->invoiceCategories()->where('state', InvoiceCategoryStateEnum::ACTIVE)->orderBy('priority', 'desc')->get();
    }


    protected function inOrganisation(InvoiceCategory $invoiceCategory, $needle): ?InvoiceCategory
    {
        if (!$needle) {
            return null;
        }

        $mode = Arr::get($invoiceCategory->settings, 'mode', 'any');
        if ($mode == 'any') {
            return $invoiceCategory;
        } else {
            return $this->inHaystack($invoiceCategory, 'organisation_ids', $needle);
        }
    }

    protected function inHaystack(InvoiceCategory $invoiceCategory, string $haystack, $needle): ?InvoiceCategory
    {
        $hay = Arr::get($invoiceCategory->settings, $haystack, []);
        if ($needle && in_array($needle, $hay)) {
            return $invoiceCategory;
        }

        return null;
    }

    protected function notInHaystack(InvoiceCategory $invoiceCategory, string $haystack, $needle): ?InvoiceCategory
    {
        $hay = Arr::get($invoiceCategory->settings, $haystack, []);
        if (!$needle || !in_array($needle, $hay)) {
            return $invoiceCategory;
        }

        return null;
    }


    protected function shopFallback(Invoice|Order $invoice, InvoiceCategory $invoiceCategory): ?InvoiceCategory
    {
        if ($invoice->shop_id == Arr::get($invoiceCategory->settings, 'shop_id')) {
            return $invoiceCategory;
        }

        return null;
    }


    protected function inShopOrSalesChannelShop(Invoice|Order $invoice, InvoiceCategory $invoiceCategory): ?InvoiceCategory
    {
        $shopsIds               = Arr::get($invoiceCategory->settings, 'shop_ids', []);
        $shopsIdsForChannelsIds = Arr::get($invoiceCategory->settings, 'shop_for_sales_channel_ids', []);
        $salesChannelsIds       = Arr::get($invoiceCategory->settings, 'sales_channel_ids', []);

        if (in_array($invoice->shop_id, $shopsIds)) {
            return $invoiceCategory;
        }

        if (in_array($invoice->shop_id, $shopsIdsForChannelsIds) && in_array($invoice->sales_channel_id, $salesChannelsIds)) {
            return $invoiceCategory;
        }

        return null;
    }

    protected function salesChannelShop(Invoice|Order $invoice, InvoiceCategory $invoiceCategory): ?InvoiceCategory
    {
        $shopsIds         = Arr::get($invoiceCategory->settings, 'shop_ids', []);
        $salesChannelsIds = Arr::get($invoiceCategory->settings, 'sales_channel_ids', []);

        if (in_array($invoice->shop_id, $shopsIds) && in_array($invoice->sales_channel_id, $salesChannelsIds)) {
            return $invoiceCategory;
        }

        return null;
    }

    public string $commandSignature = 'categorise:invoices {organisations?*} {--S|shop= : Shop slug} {--i|id=} {--e|empty : Only invoices without category} {--dry-run : Show the changes without saving them}';


    public function asCommand(Command $command): int
    {
        $command->info("Categorise invoices");
        $query = DB::table('invoices')->select('id')->orderBy('id');

        if ($command->hasOption('shop') && $command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();
            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }

        if ($command->hasOption('id') && $command->option('id')) {
            $query->where('id', $command->option('id'));
        }
        if ($command->argument('organisations')) {
            $this->getOrganisationsIds($command);
            $query->whereIn('organisation_id', $this->getOrganisationsIds($command));
        }

        if ($command->option('empty')) {
            $query->whereNull('invoice_category_id');
        }


        $count = $query->count();
        $command->info("Count: $count");

        $bar = null;

        if ($count > 1000) {
            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();
        }


        $isDryRun = (bool)$command->option('dry-run');
        $changes  = [];

        $query->chunk(1000, function (Collection $modelsData) use ($bar, $command, $isDryRun, &$changes) {
            foreach ($modelsData as $modelId) {
                $invoice            = Invoice::withTrashed()->find($modelId->id);
                $oldInvoiceCategory = $invoice->invoiceCategory;
                $newInvoiceCategory = $isDryRun ? $this->getInvoiceCategory($invoice) : $this->handle($invoice)->invoiceCategory;

                if ($oldInvoiceCategory?->id != $newInvoiceCategory?->id) {
                    $command->info("Invoice: $invoice->id $invoice->reference Category Changed:   ".$oldInvoiceCategory?->slug."     -> ".$newInvoiceCategory?->slug);
                    $change           = ($oldInvoiceCategory?->slug ?? '-').' -> '.($newInvoiceCategory?->slug ?? '-');
                    $changes[$change] = ($changes[$change] ?? 0) + 1;
                }

                $bar?->advance();
            }
        });
        if ($bar) {
            $bar->finish();
            $command->info("");
        }

        $command->table(
            ['Change', 'Invoices'],
            collect($changes)->sortDesc()->map(fn ($count, $change) => [$change, $count])->values()->all()
        );

        if ($isDryRun) {
            $command->warn('Dry run: nothing was saved');
        }

        return 0;
    }

}
