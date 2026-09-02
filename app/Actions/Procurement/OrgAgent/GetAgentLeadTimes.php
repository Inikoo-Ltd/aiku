<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Procurement\OrgAgent;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentLeadTimes
{
    use AsObject;

    public const DEFAULT_DAYS = 45;
    public const MIN_SAMPLES  = 3;

    /**
     * An agent fronts many sub-suppliers, each shipping on its own clock, so there is no single
     * agent lead time worth trusting: the per-supplier figure is what a buyer acts on, and the
     * agent figure is only the sample-weighted roll-up used when a product has no history at all.
     *
     * @return array{
     *     agent: array{days: int, source: 'measured'|'estimate'|'default', samples: int},
     *     suppliers: array<int, array{supplier_id: int, code: string, name: string, days: int, source: 'measured'|'estimate'|'default', samples: int}>
     * }
     */
    public function handle(OrgAgent $orgAgent): array
    {
        $rows = DB::table('org_suppliers as osup')
            ->join('suppliers as sup', 'sup.id', 'osup.supplier_id')
            ->leftJoin('supplier_products as sp', function ($join) {
                $join->on('sp.supplier_id', 'sup.id')
                    ->where('sp.state', SupplierProductStateEnum::ACTIVE->value)
                    ->whereNull('sp.deleted_at');
            })
            ->where('osup.org_agent_id', $orgAgent->id)
            ->groupBy('sup.id', 'sup.code', 'sup.name')
            ->selectRaw('sup.id as supplier_id, sup.code, sup.name,
                coalesce(sum(sp.lead_time_samples) filter (where sp.measured_lead_time_days is not null), 0) as measured_samples,
                coalesce(sum(sp.measured_lead_time_days * sp.lead_time_samples) filter (where sp.measured_lead_time_days is not null), 0) as measured_weighted,
                avg(sp.estimated_lead_time_days) as estimated_days')
            ->orderBy('sup.code')
            ->get();

        $suppliers = $rows->map(fn ($row) => [
            'supplier_id' => (int) $row->supplier_id,
            'code'        => $row->code,
            'name'        => $row->name,
            ...$this->resolve((int) $row->measured_samples, (float) $row->measured_weighted, $row->estimated_days),
        ])->all();

        return [
            'agent'     => $this->resolve(
                (int) $rows->sum('measured_samples'),
                (float) $rows->sum('measured_weighted'),
                $rows->avg('estimated_days')
            ),
            'suppliers' => $suppliers,
        ];
    }

    /**
     * @return array{days: int, source: 'measured'|'estimate'|'default', samples: int}
     */
    private function resolve(int $samples, float $weighted, float|int|null $estimated): array
    {
        if ($samples >= self::MIN_SAMPLES) {
            return [
                'days'    => max(1, (int) round($weighted / $samples)),
                'source'  => 'measured',
                'samples' => $samples,
            ];
        }

        if ($estimated) {
            return [
                'days'    => max(1, (int) round((float) $estimated)),
                'source'  => 'estimate',
                'samples' => $samples,
            ];
        }

        return [
            'days'    => self::DEFAULT_DAYS,
            'source'  => 'default',
            'samples' => $samples,
        ];
    }
}
