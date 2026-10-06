<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class RoundPartnerQuantityToBatches
{
    use AsObject;

    /**
     * The manufacturing hub makes whole production batches only, so what we order from it is raised to
     * the next quantity that whole batches fill exactly. Breaking a batch is a deliberate choice the
     * caller has to ask for (break_batch), not something that happens by typing a number.
     */
    public function handle(OrgPartner $orgPartner, int $stockId, float $quantity): float
    {
        return self::roundUp($quantity, $this->quantum($orgPartner, $stockId));
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

    public static function roundUp(?float $quantity, int $quantum): ?float
    {
        return $quantity === null || $quantum <= 1 ? $quantity : ceil(round($quantity / $quantum, 6)) * $quantum;
    }

    /**
     * The smallest order, in SKOs, that whole batches of the hub's artefact fill exactly, 1 without a
     * batch size (same rule as BatchedUnitsForDemand::quantumInSkos).
     */
    public static function quantumSql(string $hubOrgStock): string
    {
        return "coalesce((select a.recommended_batch_size / gcd(a.recommended_batch_size, greatest(coalesce($hubOrgStock.packed_in, 1), 1)::int)
            from artefacts a
            where a.org_stock_id = $hubOrgStock.id and a.deleted_at is null and a.recommended_batch_size > 0
            limit 1), 1)";
    }
}
