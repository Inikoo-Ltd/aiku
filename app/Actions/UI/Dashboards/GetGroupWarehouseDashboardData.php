<?php

namespace App\Actions\UI\Dashboards;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGroupWarehouseDashboardData
{
    use AsObject;

    /**
     * Stock health for the Stock tab: active SKOs by days of cover, the same levels as the procurement
     * stock cover (the Aurora quantity status stopped updating in July 2026). Warehouse work and goods
     * in live on the Operations tab.
     *
     * @return array{
     *     totals: array{stock_health: array<string, int>},
     *     stock_levels: array<int, array{bucket: string, label: string, description: string|null, tone: string}>,
     *     organisations: array<int, array{name: string, slug: string, stock_health: array<string, int>, routes: array{stock_health: array{name: string, parameters: array<string, string>}}}>
     * }
     */
    public function handle(Group $group): array
    {
        $buckets       = GetOrganisationStockCoverBuckets::make();
        $organisations = $group->organisations()->where('type', OrganisationTypeEnum::SHOP)->get()
            ->map(fn (Organisation $organisation) => [
                'name'         => $organisation->name,
                'slug'         => $organisation->slug,
                'stock_health' => $this->stockHealth($organisation),
                'routes'       => ['stock_health' => ['name' => 'grp.org.procurement.stock_cover.index', 'parameters' => ['organisation' => $organisation->slug]]],
            ]);

        return [
            'totals'        => [
                'stock_health' => collect(array_keys(GetOrganisationStockCoverBuckets::BUCKETS))
                    ->mapWithKeys(fn (string $bucket) => [$bucket => (int) $organisations->sum("stock_health.$bucket")])
                    ->all(),
            ],
            'stock_levels'  => collect(GetOrganisationStockCoverBuckets::BUCKETS)->map(fn (array $meta, string $bucket) => [
                'bucket'      => $bucket,
                'label'       => $buckets->bucketLabel($bucket),
                'description' => $buckets->bucketDescription($bucket),
                'tone'        => $meta['tone'],
            ])->values()->all(),
            'organisations' => $organisations->values()->all(),
        ];
    }

    /**
     * @return array<string, int> bucket => SKOs
     */
    private function stockHealth(Organisation $organisation): array
    {
        return Cache::remember("warehouse-dashboard-stock-cover:$organisation->id", now()->addMinutes(10), fn () => collect(GetOrganisationStockCoverBuckets::run($organisation))
            ->mapWithKeys(fn (array $bucket) => [$bucket['bucket'] => $bucket['count']])
            ->all());
    }
}
