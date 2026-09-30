<?php

namespace App\Actions\UI\Dashboards;

use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGroupWarehouseDashboardData
{
    use AsObject;

    /**
     * @return array{
     *     totals: array{work: array<string, int>, stock_health: array<string, int>, goods_in: array<string, int>},
     *     organisations: array<int, array{name: string, slug: string, work: array<string, int>, stock_health: array<string, int>, goods_in: array<string, int>, routes: array<string, array{name: string, parameters: array<string, string>}|null>}>
     * }
     */
    public function handle(Group $group): array
    {
        $group->load(['orderHandlingStats', 'procurementStats']);

        $organisations = $group->organisations()
            ->where('type', OrganisationTypeEnum::SHOP)
            ->with([
                'orderHandlingStats',
                'procurementStats',
                'warehouses' => fn ($query) => $query->select(['warehouses.id', 'warehouses.slug', 'warehouses.organisation_id']),
            ])
            ->get();

        $stockHealth = $this->stockHealth($group);

        return [
            'totals'        => $this->figures($group, $this->sumStockHealth($stockHealth)),
            'organisations' => $organisations->map(fn (Organisation $organisation) => [
                'name'   => $organisation->name,
                'slug'   => $organisation->slug,
                ...$this->figures($organisation, $stockHealth[$organisation->id] ?? $this->emptyStockHealth()),
                'routes' => $this->routes($organisation),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{work: array<string, int>, stock_health: array<string, int>, goods_in: array<string, int>}
     */
    private function figures(Group|Organisation $owner, array $stockHealth): array
    {
        $ordering    = $owner->orderHandlingStats;
        $procurement = $owner->procurementStats;

        $deliveryNotes = fn (string ...$states): int => $this->sum($ordering, array_map(fn (string $state) => 'number_delivery_notes_state_'.$state, $states));
        $stockDeliveries = fn (string ...$states): int => $this->sum($procurement, array_map(fn (string $state) => 'number_stock_deliveries_state_'.$state, $states));

        return [
            'work'         => [
                'waiting'           => $deliveryNotes('unassigned', 'queued'),
                'picking'           => $deliveryNotes('handling'),
                'blocked'           => $deliveryNotes('handling_blocked'),
                'packing'           => $deliveryNotes('picked', 'packing'),
                'ready_to_ship'     => $deliveryNotes('packed', 'finalised'),
            ],
            'stock_health' => $stockHealth,
            'goods_in'     => [
                'confirmed'            => $stockDeliveries('confirmed'),
                'on_the_way'           => $stockDeliveries('ready_to_ship', 'dispatched'),
                'to_book_in'           => $stockDeliveries('received', 'checked'),
                'booking_in'           => $stockDeliveries('booking_in'),
                'open_purchase_orders' => $this->sum($procurement, ['number_open_purchase_orders']),
            ],
        ];
    }

    /**
     * Only SKOs still sold count: the hydrated quantity-status totals include every discontinued
     * SKO, which would show most of the group as out of stock. Counting them live takes ~60 ms,
     * so the result is kept for ten minutes.
     *
     * @return array<int, array<string, int>> organisation id => quantity status => SKOs
     */
    private function stockHealth(Group $group): array
    {
        return Cache::remember("group-warehouse-stock-health:$group->id", now()->addMinutes(10), function () use ($group) {
            $byOrganisation = [];

            DB::table('org_stocks')
                ->where('group_id', $group->id)
                ->whereIn('state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value])
                ->whereNotNull('quantity_status')
                ->groupBy('organisation_id', 'quantity_status')
                ->selectRaw('organisation_id, quantity_status, count(*) as org_stocks')
                ->get()
                ->each(function ($row) use (&$byOrganisation) {
                    $byOrganisation[$row->organisation_id] ??= $this->emptyStockHealth();
                    $byOrganisation[$row->organisation_id][$this->stockHealthKey(OrgStockQuantityStatusEnum::from($row->quantity_status))] = (int) $row->org_stocks;
                });

            return $byOrganisation;
        });
    }

    /**
     * @param  array<int, array<string, int>>  $byOrganisation
     * @return array<string, int>
     */
    private function sumStockHealth(array $byOrganisation): array
    {
        $totals = $this->emptyStockHealth();
        foreach ($byOrganisation as $counts) {
            foreach ($counts as $status => $count) {
                $totals[$status] += $count;
            }
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function emptyStockHealth(): array
    {
        return collect(OrgStockQuantityStatusEnum::cases())
            ->mapWithKeys(fn (OrgStockQuantityStatusEnum $status) => [$this->stockHealthKey($status) => 0])
            ->all();
    }

    private function stockHealthKey(OrgStockQuantityStatusEnum $status): string
    {
        return str_replace('-', '_', $status->value);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function sum(?Model $stats, array $columns): int
    {
        return (int) collect($columns)->sum(fn (string $column) => $stats?->{$column} ?? 0);
    }

    /**
     * @return array<string, array{name: string, parameters: array<string, string>}|null>
     */
    private function routes(Organisation $organisation): array
    {
        $warehouseSlug = $organisation->warehouses->first()?->slug;
        $routeParams   = ['organisation' => $organisation->slug, 'warehouse' => $warehouseSlug];

        return [
            'work'         => $warehouseSlug ? ['name' => 'grp.org.warehouses.show.dispatching.backlog', 'parameters' => $routeParams] : null,
            'stock_health' => $warehouseSlug ? ['name' => 'grp.org.warehouses.show.inventory.dashboard', 'parameters' => $routeParams] : null,
            'goods_in'     => ['name' => 'grp.org.procurement.stock_deliveries.index', 'parameters' => ['organisation' => $organisation->slug]],
        ];
    }
}
