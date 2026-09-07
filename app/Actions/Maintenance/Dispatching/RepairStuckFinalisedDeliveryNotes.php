<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairStuckFinalisedDeliveryNotes
{
    use AsAction;

    public string $commandSignature = 'delivery_note:repair_stuck_finalised {organisation?} {--warehouse=} {--before=2026-01-01 : Cut-off for shops with no migrated_to_aiku_on} {--with-stuck-orders : Also dispatch orders left stuck in finalised or packed, backdated} {--with-cancelled-orders : Also cancel delivery notes whose order was cancelled} {--exclude= : Comma separated delivery note references to leave alone} {--commit : Apply the changes, otherwise dry run}';

    public function handle(DeliveryNote $deliveryNote, ?string $dispatchedAt = null): DeliveryNote
    {
        return DispatchDeliveryNote::make()->action($deliveryNote, $dispatchedAt ?? $this->fallbackDate($deliveryNote), true);
    }

    protected function isRepairable($order, Command $command): bool
    {
        if ($order->state == OrderStateEnum::DISPATCHED) {
            return true;
        }

        return (bool)$command->option('with-stuck-orders')
            && in_array($order->state, [OrderStateEnum::FINALISED, OrderStateEnum::PACKED])
            && $order->invoices()->count() > 0;
    }

    protected function fallbackDate(DeliveryNote $deliveryNote): string
    {
        return ($deliveryNote->finalised_at ? Carbon::parse($deliveryNote->finalised_at) : $deliveryNote->created_at)->toDateTimeString();
    }

    protected function auroraDispatchedAt(DeliveryNote $deliveryNote): ?string
    {
        $key = explode(':', (string)$deliveryNote->source_id)[1] ?? null;
        if (!$key) {
            return null;
        }

        $auroraDeliveryNote = DB::connection('aurora')->table('Delivery Note Dimension')
            ->where('Delivery Note Key', $key)
            ->first();

        $date = $auroraDeliveryNote?->{'Delivery Note Date Dispatched Approved'}
            ?? $auroraDeliveryNote?->{'Delivery Note Date Done Approved'};

        return $date ? Carbon::parse($date)->toDateTimeString() : null;
    }

    protected function query(Organisation $organisation, ?string $warehouse, string $before)
    {
        return DeliveryNote::where('organisation_id', $organisation->id)
            ->where('state', DeliveryNoteStateEnum::FINALISED)
            ->whereNotNull('source_id')
            ->where(function ($q) use ($before) {
                $q->whereHas('shop', fn ($s) => $s->whereColumn('delivery_notes.created_at', '<', 'shops.migrated_to_aiku_on'))
                    ->orWhereHas('shop', fn ($s) => $s->whereNull('migrated_to_aiku_on')->where('delivery_notes.created_at', '<', $before));
            })
            ->with(['orders', 'warehouse', 'shop'])
            ->when($warehouse, fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->where('slug', $warehouse)))
            ->orderBy('created_at');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $commit = (bool)$command->option('commit');
        $before = $command->option('before');

        $organisations = Organisation::whereNotNull('source_id')
            ->when($command->argument('organisation'), fn ($q) => $q->where('slug', $command->argument('organisation')))
            ->get();

        $exclude = array_filter(explode(',', (string)$command->option('exclude')));

        $repaired  = 0;
        $cancelled = 0;
        $skipped   = 0;

        foreach ($organisations as $organisation) {
            (new AuroraOrganisationService())->initialisation($organisation);

            foreach ($this->query($organisation, $command->option('warehouse'), $before)->get() as $deliveryNote) {
                if (in_array($deliveryNote->reference, $exclude)) {
                    $command->warn("EXCLUDED $deliveryNote->reference");
                    continue;
                }

                $order = $deliveryNote->orders->first();
                $where = $organisation->slug.'/'.($deliveryNote->warehouse?->slug ?? '-');

                if ($order && $order->source_id && $order->state == OrderStateEnum::CANCELLED && $command->option('with-cancelled-orders')) {
                    $cancelledAt = ($order->cancelled_at ?? $this->finalisedAt($deliveryNote))->toDateTimeString();
                    $command->line(($commit ? 'CANCELLING ' : 'WOULD CANCEL ')."$where $deliveryNote->reference at $cancelledAt — order $order->reference is cancelled");

                    if ($commit) {
                        try {
                            CancelDeliveryNote::make()->action($deliveryNote, null, false, true, $cancelledAt);
                        } catch (Throwable $e) {
                            $command->error("FAILED $deliveryNote->reference: ".$e->getMessage());
                            continue;
                        }
                    }
                    $cancelled++;
                    continue;
                }

                if (!$order || !$order->source_id || !$this->isRepairable($order, $command)) {
                    $skipped++;
                    $command->warn("NEEDS REVIEW $where $deliveryNote->reference: order ".($order?->reference ?? 'none').' is '.($order?->state->value ?? 'missing').', finalised '.$this->fallbackDate($deliveryNote));
                    continue;
                }

                $auroraDate   = $this->auroraDispatchedAt($deliveryNote);
                $dispatchedAt = $auroraDate ?? $this->fallbackDate($deliveryNote);
                $source       = $auroraDate ? 'aurora' : 'aiku';

                $orderNote = $order->state == OrderStateEnum::DISPATCHED
                    ? "order $order->reference already dispatched"
                    : "order $order->reference dispatched too, backdated";

                $command->line(($commit ? 'DISPATCHING ' : 'WOULD DISPATCH ')."$where $deliveryNote->reference ({$deliveryNote->type->value}) at $dispatchedAt [$source] — $orderNote");

                if ($commit) {
                    try {
                        $this->handle($deliveryNote, $dispatchedAt);
                    } catch (Throwable $e) {
                        $command->error("FAILED $deliveryNote->reference: ".$e->getMessage());
                        continue;
                    }
                }
                $repaired++;
            }
        }

        $command->info(($commit ? 'Dispatched' : 'Would dispatch').": $repaired delivery notes, ".($commit ? 'cancelled' : 'would cancel').": $cancelled, $skipped left for review");

        return 0;
    }
}
