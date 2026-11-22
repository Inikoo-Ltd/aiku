<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\UpcomingTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records every refund, tax refund, replacement and next-order item since records began as a
 * settled claim (HELP-4869), so a customer's claim numbers and history are true from the day claims
 * go live. Records only: no money, invoice, order, task or email is touched. Per shop, each
 * customer's claims are made oldest first, keeping the original dates, so references count up in
 * date order. Run it once before any shop goes live; already recorded items are skipped.
 */
class BackfillCustomerClaims
{
    use AsAction;

    public string $commandSignature = 'claims:backfill {--shop= : shop slug, default every shop} {--dry-run : only count}';

    public string $commandDescription = 'Record past refunds, tax refunds, replacements and next-order items as claims';

    /**
     * @return array{refund: int, tax_correction: int, replacement: int, next_order: int, made: int, customers_with_live_claims: int}
     */
    public function handle(Shop $shop, bool $dryRun = true): array
    {
        $events = collect();

        Invoice::where('shop_id', $shop->id)->where('type', InvoiceTypeEnum::REFUND)
            ->where(fn ($query) => $query->where('in_process', false)->orWhereNull('in_process'))
            ->select(['id', 'customer_id', 'date', 'created_at', 'is_tax_only'])
            ->chunkById(2000, function ($refunds) use ($events) {
                foreach ($refunds as $refund) {
                    $events->push(['customer_id' => $refund->customer_id, 'at' => ($refund->date ?? $refund->created_at)?->timestamp ?? 0, 'kind' => $refund->is_tax_only ? 'tax_correction' : 'refund', 'ids' => [$refund->id]]);
                }
            });

        DeliveryNote::where('shop_id', $shop->id)->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->where('state', '!=', DeliveryNoteStateEnum::CANCELLED)
            ->select(['id', 'customer_id', 'created_at'])
            ->chunkById(2000, function ($notes) use ($events) {
                foreach ($notes as $note) {
                    $events->push(['customer_id' => $note->customer_id, 'at' => $note->created_at?->timestamp ?? 0, 'kind' => 'replacement', 'ids' => [$note->id]]);
                }
            });

        UpcomingTransaction::where('shop_id', $shop->id)->where('type', UpcomingTransactionTypeEnum::FOLLOW_ON)
            ->select(['id', 'customer_id', 'created_at'])->orderBy('id')->get()
            ->groupBy(fn ($followOn) => $followOn->customer_id.'|'.$followOn->created_at?->format('Y-m-d H:i'))
            ->each(fn ($group) => $events->push(['customer_id' => $group->first()->customer_id, 'at' => $group->first()->created_at?->timestamp ?? 0, 'kind' => 'next_order', 'ids' => $group->pluck('id')->all()]));

        $counts = ['refund' => 0, 'tax_correction' => 0, 'replacement' => 0, 'next_order' => 0, 'made' => 0];
        foreach ($events as $event) {
            $counts[$event['kind']]++;
        }
        $counts['customers_with_live_claims'] = CustomerClaim::where('shop_id', $shop->id)->whereNull('result->backfilled')->distinct('customer_id')->count('customer_id');

        if ($dryRun) {
            return $counts;
        }

        $recorder = RecordStaffClaim::make();
        foreach ($events->sortBy([['customer_id', 'asc'], ['at', 'asc']]) as $event) {
            $claim = match ($event['kind']) {
                'refund', 'tax_correction' => ($refund = Invoice::find($event['ids'][0])) ? $recorder->backfillRefund($refund) : null,
                'replacement'              => ($note = DeliveryNote::find($event['ids'][0])) ? $recorder->backfillReplacement($note) : null,
                'next_order'               => $recorder->backfillFollowOns(UpcomingTransaction::whereIn('id', $event['ids'])->with('product')->get()),
            };
            if ($claim) {
                $counts['made']++;
            }
        }

        return $counts;
    }

    public function asCommand(Command $command): int
    {
        $shops = Shop::query()
            ->when($command->option('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('id')
            ->get();
        $dryRun = (bool) $command->option('dry-run');
        $rows   = [];

        DB::disableQueryLog();
        foreach ($shops as $shop) {
            $counts = $this->handle($shop, $dryRun);
            $rows[] = [$shop->slug, $counts['refund'], $counts['tax_correction'], $counts['replacement'], $counts['next_order'], $dryRun ? '-' : $counts['made'], $counts['customers_with_live_claims']];
        }

        $command->table(['shop', 'refunds', 'tax corrections', 'replacements', 'next-order groups', 'claims made', 'customers with live claims'], $rows);
        if ($dryRun) {
            $command->info('Dry run: nothing written. Run again without --dry-run to record them.');
        }

        return 0;
    }
}
