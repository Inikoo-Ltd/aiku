<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class RoundPartnerQuantityToBatches
{
    use AsObject;

    /**
     * The manufacturing hub makes whole production batches only, so what we order from it is raised to
     * the next whole order step (one batch in whole SKOs), unless the hub's free shelf stock covers it:
     * then nothing is made and the line is served from the shelf. Breaking a batch beyond that is a
     * deliberate choice the caller has to ask for (break_batch), not something that happens by typing a number.
     */
    public function handle(OrgPartner $orgPartner, int $stockId, float $quantity, ?int $exceptItemId = null): float
    {
        $quantum = $this->quantum($orgPartner, $stockId);
        if ($quantum <= 1) {
            return $quantity;
        }

        return self::roundUpPastFreeStock($quantity, $quantum, self::freeSkosByStockId($orgPartner, [$stockId], $exceptItemId)[$stockId] ?? 0);
    }

    public static function roundUpPastFreeStock(?float $quantity, int $quantum, float $freeSkos): ?float
    {
        return $quantity !== null && $quantity <= $freeSkos ? $quantity : self::roundUp($quantity, $quantum);
    }

    /**
     * The hub's shelf stock no buyer has a claim on yet: available, less what is pre-picked but not staged
     * (PartnerShoppingListItem::freeStockSql) and less every buyer's open lines still queued for the shelf.
     * $exceptItemId leaves out the line being changed, so it does not compete with itself.
     *
     * @param  array<int, int>  $stockIds
     * @return array<int, float> stock id => free hub SKOs
     */
    public static function freeSkosByStockId(OrgPartner $orgPartner, array $stockIds, ?int $exceptItemId = null): array
    {
        return array_map(fn (float $free) => max(0.0, $free), self::unclaimedSkosByStockId($orgPartner, $stockIds, $exceptItemId));
    }

    /**
     * Same as freeSkosByStockId but not floored at zero, so a queued line's own quantity can be given back to it
     * before flooring (shelf 18, lines 15 and 15: the second one has 3 free, not 15).
     *
     * @param  array<int, int>  $stockIds
     * @return array<int, float> stock id => hub SKOs left after every claim, negative when over-claimed
     */
    public static function unclaimedSkosByStockId(OrgPartner $orgPartner, array $stockIds, ?int $exceptItemId = null): array
    {
        if (!$orgPartner->partner->is_manufacturing_hub || !$stockIds) {
            return [];
        }

        $sellerId = (int) $orgPartner->partner_id;
        $except   = $exceptItemId ? 'and queued.id <> '.(int) $exceptItemId : '';

        return DB::table('org_stocks as hub_org_stock')
            ->where('hub_org_stock.organisation_id', $sellerId)
            ->whereIn('hub_org_stock.stock_id', $stockIds)
            ->selectRaw("hub_org_stock.stock_id, (coalesce(hub_org_stock.quantity_available, 0)
                - ".PartnerShoppingListItem::promisedNotStagedSql((string) $sellerId, 'hub_org_stock.stock_id')."
                - (select coalesce(sum(queued.quantity), 0) from partner_shopping_list_items queued
                    where queued.partner_organisation_id = $sellerId
                        and queued.stock_id = hub_org_stock.stock_id
                        and queued.state = 'open'
                        and queued.pre_picked_at is null
                        and queued.job_order_id is null
                        and queued.preparing_at is null
                        and queued.deleted_at is null
                        $except)) as free")
            ->pluck('free', 'stock_id')
            ->map(fn ($free) => (float) $free)
            ->all();
    }

    public function quantum(OrgPartner $orgPartner, int $stockId): int
    {
        if (!$orgPartner->partner->is_manufacturing_hub) {
            return 1;
        }

        return (int) (DB::table('org_stocks as hub_org_stock')
            ->where('hub_org_stock.organisation_id', $orgPartner->partner_id)
            ->where('hub_org_stock.stock_id', $stockId)
            ->selectRaw(self::quantumSql('hub_org_stock').' as quantum')
            ->value('quantum') ?? 1);
    }

    /**
     * The order step of each of the hub's own SKOs on a page, in one query.
     *
     * @param  array<int, int>  $hubOrgStockIds
     * @return array<int, int> hub org stock id => SKOs per step
     */
    public function quanta(OrgPartner $orgPartner, array $hubOrgStockIds): array
    {
        if (!$orgPartner->partner->is_manufacturing_hub || !$hubOrgStockIds) {
            return [];
        }

        return DB::table('org_stocks as hub_org_stock')
            ->whereIn('hub_org_stock.id', $hubOrgStockIds)
            ->selectRaw('hub_org_stock.id, '.self::quantumSql('hub_org_stock').' as quantum')
            ->pluck('quantum', 'id')
            ->map(fn ($quantum) => (int) $quantum)
            ->all();
    }

    /**
     * The hub's order step in SKOs per stock, in one query.
     *
     * @param  array<int, int>  $stockIds
     * @return array<int, int> stock id => SKOs per step
     */
    public static function quantaByStockId(OrgPartner $orgPartner, array $stockIds): array
    {
        if (!$orgPartner->partner->is_manufacturing_hub || !$stockIds) {
            return [];
        }

        return DB::table('org_stocks as hub_org_stock')
            ->where('hub_org_stock.organisation_id', $orgPartner->partner_id)
            ->whereIn('hub_org_stock.stock_id', $stockIds)
            ->selectRaw('hub_org_stock.stock_id, '.self::quantumSql('hub_org_stock').' as quantum')
            ->pluck('quantum', 'stock_id')
            ->map(fn ($quantum) => (int) $quantum)
            ->all();
    }

    public static function roundUp(?float $quantity, int $quantum): ?float
    {
        return $quantity === null || $quantum <= 1 ? $quantity : ceil(round($quantity / $quantum, 6)) * $quantum;
    }

    /**
     * The order step, in SKOs: one batch of the hub's artefact rounded down to whole SKOs, 1 without a
     * batch size (same rule as BatchedUnitsForDemand::quantumInSkos).
     */
    public static function quantumSql(string $hubOrgStock): string
    {
        return "coalesce((select greatest(1, a.recommended_batch_size / greatest(coalesce($hubOrgStock.packed_in, 1), 1)::int)
            from artefacts a
            where a.org_stock_id = $hubOrgStock.id and a.deleted_at is null and a.recommended_batch_size > 0
            limit 1), 1)";
    }
}
