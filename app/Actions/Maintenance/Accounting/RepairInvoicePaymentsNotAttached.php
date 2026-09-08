<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * An invoice whose order carries the payments but which has no payment of its own reads
 * as unpaid, because UpdateInvoicePaymentState only ever looks at the invoice side of
 * model_has_payments. This attaches the order's payments to the invoice, and only when
 * the arithmetic leaves no room for judgement: one invoice on the order, and the
 * payments summing to the invoice total to the penny. Anything else is reported and
 * left for a human.
 */
class RepairInvoicePaymentsNotAttached
{
    use AsAction;

    public string $commandSignature = 'repair:invoice_payments_not_attached {--S|shop= : Shop slug} {--s|slug=* : Invoice slugs} {--B|before-migration : Only invoices dated before the shop moved to aiku} {--apply}';

    public function handle(Invoice $invoice, Collection $payments): void
    {
        foreach ($payments as $payment) {
            AttachPaymentToInvoice::make()->action($invoice, $payment, ['amount' => $payment->amount]);
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $apply    = $command->option('apply');
        $repaired = 0;
        $skipped  = [];

        $query = Invoice::where('type', InvoiceTypeEnum::INVOICE)
            ->where('pay_status', InvoicePayStatusEnum::UNPAID)
            ->where('in_process', false)
            ->where('total_amount', '>', 0)
            ->whereNotNull('order_id')
            ->whereDoesntHave('payments')
            ->orderBy('id');

        if ($shopSlug = $command->option('shop')) {
            $query->where('shop_id', Shop::where('slug', $shopSlug)->firstOrFail()->id);
        }

        if ($slugs = $command->option('slug')) {
            $query->whereIn('slug', $slugs);
        }

        // Before the shop moved over, aurora was the book of record and aiku only mirrored
        // it, so a payment state corrected here was never the figure anyone reported on.
        if ($command->option('before-migration')) {
            $query->whereHas('shop', function ($q) {
                $q->whereNotNull('shops.migrated_to_aiku_on')
                    ->whereColumn('invoices.date', '<', 'shops.migrated_to_aiku_on');
            });
        }

        foreach ($query->with(['order', 'shop'])->cursor() as $invoice) {
            $order = $invoice->order;

            if (!$order) {
                $skipped[] = [$invoice->reference, 'no order', (float) $invoice->total_amount, ''];
                continue;
            }

            // An order can carry several invoices, and a payment already spoken for by one
            // of them is not available to another. Only what is left over counts, which for
            // an order holding a single invoice is simply all of its payments.
            $siblingInvoiceIds = $order->invoices()->pluck('id');

            $payments = $order->payments()
                ->where('payments.status', PaymentStatusEnum::SUCCESS)
                ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
                ->whereDoesntHave('invoices', fn ($q) => $q->whereIn('invoices.id', $siblingInvoiceIds))
                ->get();

            $paid = round($payments->sum('amount'), 2);

            if ($payments->isEmpty() || $paid != round($invoice->total_amount, 2)) {
                $skipped[] = [$invoice->reference, 'payments do not sum to the total', (float) $invoice->total_amount, $paid];
                continue;
            }

            // Before the shop moved over, aurora was the book of record and aiku only
            // mirrored it, so an unlinked payment there is import damage and nothing else.
            // After it, aiku owns the data: an aurora sourced payment is still the old
            // damage arriving late, but one raised in aiku may be sitting off the invoice
            // for a reason this command cannot see. Leave those for a human.
            $migratedOn  = $invoice->shop->migrated_to_aiku_on;
            $preMigration = $migratedOn && $invoice->date < $migratedOn;

            if (!$preMigration && $payments->contains(fn ($payment) => blank($payment->source_id))) {
                $skipped[] = [$invoice->reference, 'payment raised in aiku, not aurora', (float) $invoice->total_amount, $paid];
                continue;
            }

            $command->line(($apply ? 'repairing ' : 'would repair ').$invoice->reference.' '.$order->reference.' '.$paid.' ('.$payments->count().' payment(s))');

            if ($apply) {
                $this->handle($invoice, $payments);

                $invoice->refresh();
                if ($invoice->pay_status !== InvoicePayStatusEnum::PAID) {
                    $command->error('  '.$invoice->reference.' did not settle, it is now '.$invoice->pay_status->value);
                }
            }
            $repaired++;
        }

        if ($skipped) {
            $command->warn('Needs a human, left untouched:');
            $command->table(['invoice', 'reason', 'total', 'paid on order'], $skipped);
        }

        $command->info(($apply ? 'Repaired ' : 'Would repair ').$repaired.' invoice(s), skipped '.count($skipped).'.');

        return 0;
    }
}
