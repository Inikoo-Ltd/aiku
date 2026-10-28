<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Procurement;

use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\SupplyChain\SupplierPortalContact\SyncSupplierPortalContact;
use App\Models\SupplyChain\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An email logs in for one supplier only. Where active suppliers share one (mostly the same company entered twice),
 * the supplier we buy from keeps it: most purchase orders in the last 12 months, then the latest, then the most,
 * then the most active products. The others lose it as a log in email; when it was their main email it moves to
 * their purchase order email, so purchase orders still reach the supplier. Dry run unless --apply.
 */
class RepairSupplierPortalDuplicatedEmails
{
    use AsAction;

    public string $commandSignature = 'repair:supplier_portal_duplicated_emails {--apply : change the suppliers, otherwise only show what would change}';

    /**
     * @return Collection<string, Collection<int, Supplier>>
     */
    public function duplicates(): Collection
    {
        return Supplier::where('status', true)->get()
            ->flatMap(fn (Supplier $supplier) => collect(SyncSupplierPortalContact::emails($supplier))->map(fn (string $email) => ['email' => $email, 'supplier' => $supplier]))
            ->groupBy('email')
            ->filter(fn (Collection $rows) => $rows->count() > 1)
            ->map(fn (Collection $rows) => $this->ranked($rows->pluck('supplier')));
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     *
     * @return Collection<int, Supplier>
     */
    private function ranked(Collection $suppliers): Collection
    {
        $stats = DB::table('purchase_orders')
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->groupBy('supplier_id')
            ->selectRaw("supplier_id, count(*) filter (where date > now() - interval '12 months') as recent, max(date) as last, count(*) as total")
            ->get()->keyBy('supplier_id');

        $products = DB::table('supplier_products')
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->where('state', '!=', 'discontinued')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, count(*) as total')
            ->pluck('total', 'supplier_id');

        return $suppliers->each(function (Supplier $supplier) use ($stats, $products) {
            $supplier->setAttribute('rank_recent_pos', (int) ($stats[$supplier->id]->recent ?? 0));
            $supplier->setAttribute('rank_last_po', $stats[$supplier->id]->last ?? null);
            $supplier->setAttribute('rank_pos', (int) ($stats[$supplier->id]->total ?? 0));
            $supplier->setAttribute('rank_products', (int) ($products[$supplier->id] ?? 0));
        })->sort(fn (Supplier $a, Supplier $b) => [$b->rank_recent_pos, (string) $b->rank_last_po, $b->rank_pos, $b->rank_products, $a->id]
            <=> [$a->rank_recent_pos, (string) $a->rank_last_po, $a->rank_pos, $a->rank_products, $b->id])->values();
    }

    public function handle(Supplier $supplier, string $email): void
    {
        $extraEmails = $supplier->data['extra_emails'] ?? [];
        $modelData   = [];

        if (mb_strtolower(trim((string) $supplier->email)) === $email) {
            $modelData['email'] = null;
            if (!Arr::get($supplier->settings, 'po_email')) {
                $modelData['po_email'] = $supplier->email;
            }
        }

        $remaining = array_values(array_filter($extraEmails, fn ($extraEmail) => mb_strtolower(trim((string) $extraEmail)) !== $email));
        if (count($remaining) !== count($extraEmails)) {
            $modelData['extra_emails'] = $remaining;
        }

        UpdateSupplier::make()->action($supplier, $modelData, strict: false);
        SyncSupplierPortalContact::run($supplier->refresh());
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $duplicates = $this->duplicates();
        if ($duplicates->isEmpty()) {
            $command->info('No email logs in for more than one supplier.');

            return 0;
        }

        $rows = [];
        foreach ($duplicates as $email => $suppliers) {
            foreach ($suppliers as $index => $supplier) {
                $rows[] = [
                    $email,
                    $supplier->id,
                    $supplier->code,
                    $supplier->rank_recent_pos,
                    $supplier->rank_last_po ? substr($supplier->rank_last_po, 0, 10) : '-',
                    $supplier->rank_pos,
                    $supplier->rank_products,
                    $index === 0 ? 'KEEPS' : (mb_strtolower(trim((string) $supplier->email)) === $email ? 'loses (moved to PO email)' : 'loses'),
                ];
            }
        }
        $command->table(['Email', 'Id', 'Supplier', 'POs 12m', 'Last PO', 'POs', 'Products', 'Log in email'], $rows);

        if (!$command->option('apply')) {
            $command->warn('Dry run: nothing changed. Run again with --apply.');

            return 0;
        }

        foreach ($duplicates as $email => $suppliers) {
            foreach ($suppliers->slice(1) as $supplier) {
                $this->handle($supplier, $email);
            }
        }
        $command->info($duplicates->count().' emails now log in for one supplier only.');

        return 0;
    }
}
