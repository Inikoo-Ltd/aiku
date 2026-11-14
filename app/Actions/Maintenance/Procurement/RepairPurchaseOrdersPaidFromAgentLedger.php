<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Procurement;

use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderPaidFromAgentLedger;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * INI-240. Agent orders whose supplier payments were in the agent's ledger before the ledger set the deposit and
 * balance dates (INI-248). Only open orders (submitted to arrived): settled and cancelled ones are left as they
 * are. An empty date takes the ledger's, a date already set is kept. Nothing is written without --apply.
 */
class RepairPurchaseOrdersPaidFromAgentLedger
{
    use AsAction;

    public string $commandSignature = 'repair:purchase_orders_paid_from_agent_ledger {--apply : Write the changes; without it nothing is written} {--id=* : Only these purchase order ids}';

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, array{id: int, reference: string, state: string, deposit_paid_at: string|null, balance_paid_at: string|null}>
     */
    public function handle(bool $apply = false, array $ids = []): Collection
    {
        $none = ['deposit_paid_at' => null, 'balance_paid_at' => null];

        return PurchaseOrder::query()
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->whereIn('state', PurchaseOrderStateEnum::openValues())
            ->where(fn ($query) => $query->whereNull('deposit_paid_at')->orWhereNull('balance_paid_at'))
            ->whereExists(fn ($query) => $query->from('agent_payments')
                ->whereColumn('agent_payments.purchase_order_id', 'purchase_orders.id')
                ->whereIn('agent_payments.type', AgentPaymentTypeEnum::supplierPaymentValues())
                ->whereNull('agent_payments.deleted_at')
                ->whereNotNull('agent_payments.date'))
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->isAgentOrder())
            ->map(function (PurchaseOrder $purchaseOrder) use ($apply, $none) {
                $ledger = SyncPurchaseOrderPaidFromAgentLedger::ledgerDates($purchaseOrder);
                $row    = [
                    'id'              => $purchaseOrder->id,
                    'reference'       => $purchaseOrder->reference,
                    'state'           => $purchaseOrder->state->value,
                    'deposit_paid_at' => $purchaseOrder->deposit_paid_at === null ? $ledger['deposit_paid_at'] : null,
                    'balance_paid_at' => $purchaseOrder->balance_paid_at === null ? $ledger['balance_paid_at'] : null,
                ];

                if ($apply && ($row['deposit_paid_at'] || $row['balance_paid_at'])) {
                    SyncPurchaseOrderPaidFromAgentLedger::run($purchaseOrder, $none);
                }

                return $row;
            })
            ->filter(fn (array $row) => $row['deposit_paid_at'] || $row['balance_paid_at'])
            ->values();
    }

    public function asCommand(Command $command): int
    {
        $apply = (bool) $command->option('apply');
        $rows  = $this->handle($apply, array_map('intval', $command->option('id')));

        $command->table(['id', 'reference', 'state', 'deposit paid', 'balance paid'], $rows->map(fn (array $row) => array_values($row))->all());
        $command->info(($apply ? 'Updated ' : 'Would update ').$rows->count().' orders: '
            .$rows->whereNotNull('deposit_paid_at')->count().' deposit dates, '
            .$rows->whereNotNull('balance_paid_at')->count().' balance dates, '
            .$rows->where('state', 'submitted')->whereNotNull('deposit_paid_at')->count().' submitted orders confirmed');

        return 0;
    }
}
