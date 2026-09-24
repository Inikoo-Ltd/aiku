<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange;

use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The state a change type touches, read the same way before a change, after it and before a
 * revert: a revert only goes ahead while the current snapshot still equals the stored after.
 */
class GetMcpChangeSnapshot
{
    use AsObject;

    public function handle(McpChangeTypeEnum $type, array $target): array
    {
        return match ($type) {
            McpChangeTypeEnum::RELATED_PRODUCTS => $this->relatedProducts($target),
            McpChangeTypeEnum::ORG_STOCK_STATE  => $this->orgStockStates($target),
        };
    }

    public function describe(McpChangeTypeEnum $type, array $target, array $snapshot): string
    {
        if ($type === McpChangeTypeEnum::ORG_STOCK_STATE) {
            return collect($snapshot['org_stocks'])
                ->map(fn (array $orgStock) => $orgStock['code'].': '.$orgStock['state'].($orgStock['scheduled'] ? ' (scheduled '.$orgStock['scheduled']['to_state'].')' : ''))
                ->implode(', ');
        }

        $codes = DB::table($target['level'] === 'master' ? 'master_assets' : 'products')
            ->whereIn('id', $snapshot['ids'])
            ->pluck('code', 'id');

        return collect($snapshot['ids'])->map(fn ($id) => $codes[$id] ?? '#'.$id)->implode(', ') ?: '-';
    }

    private function relatedProducts(array $target): array
    {
        $query = $target['level'] === 'master'
            ? DB::table('master_product_category_has_related_assets')->where('master_product_category_id', $target['id'])->select('master_asset_id as id')
            : DB::table('product_category_has_related_products')->where('product_category_id', $target['id'])->select('product_id as id');

        return ['ids' => $query->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    private function orgStockStates(array $target): array
    {
        return [
            'org_stocks' => OrgStock::whereIn('id', $target['org_stock_ids'])
                ->with('organisation:id,code')
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (OrgStock $orgStock) => [
                    $orgStock->id => [
                        'code'      => $orgStock->code.' ('.$orgStock->organisation->code.')',
                        'state'     => $orgStock->state->value,
                        'scheduled' => Arr::get($orgStock->data, DiscontinueOrgStocks::SCHEDULED_KEY),
                    ],
                ])
                ->all(),
        ];
    }
}
