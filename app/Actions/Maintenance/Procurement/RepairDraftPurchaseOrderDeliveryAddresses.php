<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 09:11
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Maintenance\Procurement;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Drafts used to copy the organisation's delivery address when they were created, so a later change in the
 * procurement settings never reached them. A draft whose copy is still one of the organisation's own addresses (a
 * warehouse or the procurement setting) loses the copy and follows the organisation again; an address typed for
 * that one order stays. Nothing is written without --apply.
 */
class RepairDraftPurchaseOrderDeliveryAddresses
{
    use AsAction;

    public string $commandSignature = 'repair:draft_purchase_order_delivery_addresses {--apply : Write the changes; without it nothing is written}';

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function handle(bool $apply = false): Collection
    {
        $drafts = Organisation::query()->get()->flatMap(fn (Organisation $organisation) => $this->draftsCopyingOwnAddress($organisation));

        if ($apply) {
            $drafts->each(function (PurchaseOrder $purchaseOrder) {
                $purchaseOrder->data = Arr::except($purchaseOrder->data ?? [], 'delivery_address');
                $purchaseOrder->saveQuietly();
            });
        }

        return $drafts;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    private function draftsCopyingOwnAddress(Organisation $organisation): Collection
    {
        $ownAddresses = $organisation->warehouses()->with('address')->get()
            ->map(fn ($warehouse) => $warehouse->address?->formatted_address)
            ->push(Arr::get($organisation->settings, 'procurement.delivery_address'))
            ->filter()
            ->map(fn (string $address) => trim($address))
            ->unique()
            ->values();

        if ($ownAddresses->isEmpty()) {
            return collect();
        }

        return PurchaseOrder::query()
            ->where('organisation_id', $organisation->id)
            ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
            ->whereNotNull('data->delivery_address')
            ->get(['id', 'organisation_id', 'reference', 'data'])
            ->filter(fn (PurchaseOrder $purchaseOrder) => $ownAddresses->contains(trim((string) Arr::get($purchaseOrder->data, 'delivery_address'))))
            ->values();
    }

    public function asCommand(Command $command): int
    {
        $apply  = (bool) $command->option('apply');
        $drafts = $this->handle($apply);

        $command->table(
            ['Organisation', 'Drafts'],
            $drafts->groupBy('organisation_id')
                ->map(fn (Collection $orders, int $organisationId) => [Organisation::find($organisationId)?->slug, $orders->count()])
                ->values()
                ->all()
        );

        $command->info(($apply ? 'Repaired ' : 'Would repair ').$drafts->count().' draft purchase orders.');
        if (!$apply) {
            $command->line('Nothing was written. Run again with --apply to write.');
        }

        return 0;
    }
}
