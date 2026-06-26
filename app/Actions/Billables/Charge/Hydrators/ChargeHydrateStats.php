<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\Hydrators;

use App\Models\Billables\Charge;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ChargeHydrateStats implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales_slave';
    public string $commandSignature = 'charge:hydrate-stats';

    public function getJobUniqueId(int $chargeId): string
    {
        return $chargeId;
    }

    public function handle(int $chargeId): void
    {
        $charge = Charge::find($chargeId);

        if (!$charge) {
            return;
        }

        $stats = $this->calculateStats($charge);

        $charge->stats()->updateOrCreate(
            ['charge_id' => $charge->id],
            $stats
        );
    }

    public function asCommand(Command $command): void
    {
        $total = 0;
        $errors = 0;

        $bar = $command->getOutput()->createProgressBar(Charge::count());
        $bar->start();

        Charge::chunk(50, function ($charges) use (&$total, &$errors, $bar) {
            foreach ($charges as $charge) {
                try {
                    static::dispatch($charge->id);
                    $total++;
                } catch (\Exception $e) {
                    $command->getOutput()->writeln("Error charge_id={$charge->id}: {$e->getMessage()}");
                    $errors++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $command->getOutput()->newLine();
        $command->info("Dispatched {$total} charges for hydration" . ($errors ? ", {$errors} errors" : ''));
    }

    protected function calculateStats(Charge $charge): array
    {
        $invoiceTransactionData = DB::connection('aiku_no_sticky')
            ->table('invoice_transactions')
            ->selectRaw('COUNT(DISTINCT customer_id) as number_customers')
            ->selectRaw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as number_invoices')
            ->selectRaw('COUNT(DISTINCT order_id) as number_orders')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(org_net_amount), 0) as org_amount')
            ->selectRaw('COALESCE(SUM(grp_net_amount), 0) as grp_amount')
            ->selectRaw('MIN(date) as first_used_at')
            ->selectRaw('MAX(date) as last_used_at')
            ->where('model_type', 'Charge')
            ->where('model_id', $charge->id)
            ->whereNull('deleted_at')
            ->first();

        return [
            'number_customers'      => $invoiceTransactionData->number_customers ?? 0,
            'number_orders'         => $invoiceTransactionData->number_orders ?? 0,
            'number_invoices'       => $invoiceTransactionData->number_invoices ?? 0,
            'number_delivery_notes' => 0,
            'amount'                => $invoiceTransactionData->amount ?? 0,
            'org_amount'            => $invoiceTransactionData->org_amount ?? 0,
            'grp_amount'            => $invoiceTransactionData->grp_amount ?? 0,
            'first_used_at'         => $invoiceTransactionData->first_used_at,
            'last_used_at'          => $invoiceTransactionData->last_used_at,
        ];
    }
}
