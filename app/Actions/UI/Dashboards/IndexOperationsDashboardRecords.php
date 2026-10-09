<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Picking;
use App\Models\GoodsIn\OrderReturn;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The rows behind the Operations (In/Out) numbers that have no list of their own elsewhere: open
 * delivery notes by age, dispatch times, locations not counted, today's picks, short picks and packs,
 * and this month's processed returns and return reasons. Each list repeats the dashboard's own
 * conditions so it holds exactly what was counted. Picks and packs show the work, not who did it:
 * named figures wait for HR approval.
 */
class IndexOperationsDashboardRecords extends OrgAction
{
    public const array LISTS = [
        'age_under_4h', 'age_4_24h', 'age_1_2d', 'age_over_2d',
        'dispatched', 'not_counted', 'picked_today', 'short_today', 'packed_today',
        'returns_processed', 'return_reasons',
    ];

    private string $list;

    public function rules(): array
    {
        return [
            'channel' => ['sometimes', 'nullable', Rule::enum(ShopTypeEnum::class)],
            'period'  => ['sometimes', 'nullable', 'integer', Rule::in(GetOperationsDashboardData::PERIODS)],
            'reason'  => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return GetOperationsDashboardData::warehousesFor($request->user())->contains('id', $this->warehouse->id);
    }

    public function handle(Warehouse $warehouse, string $list, array $filters): LengthAwarePaginator
    {
        $timezone = $warehouse->organisation->timezone?->name ?? 'UTC';
        $now      = now($timezone);
        $channel  = $filters['channel'] ?? null;

        $query = match ($list) {
            'age_under_4h', 'age_4_24h', 'age_1_2d', 'age_over_2d' => $this->openByAge($warehouse, $channel, $list, $now),
            'dispatched'        => $this->dispatched($warehouse, $channel, (int) ($filters['period'] ?? 30), $now),
            'not_counted'       => $this->notCounted($warehouse),
            'picked_today'      => $this->pickedToday($warehouse, $channel, $now, 'pick'),
            'short_today'       => $this->pickedToday($warehouse, $channel, $now, 'not-pick'),
            'packed_today'      => $this->packedToday($warehouse, $channel, $now),
            'returns_processed' => $this->returnsProcessed($warehouse, $now),
            'return_reasons'    => $this->returnReasons($warehouse, $now, $filters['reason'] ?? null),
        };

        $paginator = $query->withPaginator(null, tableName: request()->route()?->getName())->withQueryString();

        $paginator->getCollection()->transform(fn ($row) => $this->row($row->getAttributes(), $warehouse, $timezone));

        return $paginator;
    }

    private function deliveryNotes(Warehouse $warehouse, ?string $channel): QueryBuilder
    {
        return QueryBuilder::for(DeliveryNote::class)
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->leftJoin('customers', 'customers.id', 'delivery_notes.customer_id')
            ->select('delivery_notes.slug', 'delivery_notes.reference', 'delivery_notes.state', 'delivery_notes.date', 'customers.name as customer_name');
    }

    private function openByAge(Warehouse $warehouse, ?string $channel, string $list, Carbon $now): QueryBuilder
    {
        $utcNow = $now->copy()->utc();
        [$from, $to] = match ($list) {
            'age_under_4h' => [$utcNow->copy()->subHours(4), null],
            'age_4_24h'    => [$utcNow->copy()->subDay(), $utcNow->copy()->subHours(4)],
            'age_1_2d'     => [$utcNow->copy()->subDays(2), $utcNow->copy()->subDay()],
            'age_over_2d'  => [null, $utcNow->copy()->subDays(2)],
        };

        $openStates = array_map(fn (DeliveryNoteStateEnum $state) => $state->value, array_merge(...array_values(GetOperationsDashboardData::STAGES)));

        return $this->deliveryNotes($warehouse, $channel)
            ->whereIn('delivery_notes.state', $openStates)
            ->when($from, fn (Builder $query) => $query->where('delivery_notes.date', '>', $from))
            ->when($to, fn (Builder $query) => $query->where('delivery_notes.date', '<=', $to))
            ->defaultSort('date')
            ->allowedSorts(['date', 'reference', 'state', 'customer_name']);
    }

    private function dispatched(Warehouse $warehouse, ?string $channel, int $period, Carbon $now): QueryBuilder
    {
        return $this->deliveryNotes($warehouse, $channel)
            ->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED->value)
            ->where('delivery_notes.type', 'order')
            ->where('delivery_notes.dispatched_at', '>=', $now->copy()->startOfDay()->subDays($period - 1)->utc())
            ->whereColumn('delivery_notes.dispatched_at', '>=', 'delivery_notes.date')
            ->addSelect('delivery_notes.dispatched_at')
            ->selectRaw('extract(epoch from delivery_notes.dispatched_at - delivery_notes.date)::bigint as time_to_dispatch')
            ->defaultSort('-time_to_dispatch')
            ->allowedSorts(['time_to_dispatch', 'dispatched_at', 'date', 'reference', 'customer_name']);
    }

    private function notCounted(Warehouse $warehouse): QueryBuilder
    {
        return QueryBuilder::for(Location::class)
            ->where('locations.warehouse_id', $warehouse->id)
            ->where('locations.is_empty', false)
            ->where(fn (Builder $query) => $query->whereNull('locations.audited_at')->orWhere('locations.audited_at', '<', now()->subDays(90)))
            ->leftJoin('warehouse_areas', 'warehouse_areas.id', 'locations.warehouse_area_id')
            ->select('locations.slug', 'locations.code', 'locations.audited_at', 'warehouse_areas.name as area')
            ->defaultSort('audited_at')
            ->allowedSorts(['audited_at', 'code', 'area']);
    }

    private function pickedToday(Warehouse $warehouse, ?string $channel, Carbon $now, string $type): QueryBuilder
    {
        return QueryBuilder::for(Picking::class)
            ->join('delivery_notes', 'delivery_notes.id', 'pickings.delivery_note_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'pickings.org_stock_id')
            ->leftJoin('locations', 'locations.id', 'pickings.location_id')
            ->where('pickings.organisation_id', $warehouse->organisation_id)
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->where('pickings.type', $type)
            ->where('pickings.created_at', '>=', $now->copy()->startOfDay()->utc())
            ->select(
                'pickings.created_at',
                'pickings.quantity',
                'pickings.not_picked_reason',
                'delivery_notes.slug',
                'delivery_notes.reference',
                'org_stocks.code as org_stock_code',
                'org_stocks.slug as org_stock_slug',
                'locations.code as location_code',
            )
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'reference', 'org_stock_code', 'quantity']);
    }

    private function packedToday(Warehouse $warehouse, ?string $channel, Carbon $now): QueryBuilder
    {
        return $this->deliveryNotes($warehouse, $channel)
            ->where('delivery_notes.packed_at', '>=', $now->copy()->startOfDay()->utc())
            ->whereNotNull('delivery_notes.packer_user_id')
            ->addSelect('delivery_notes.packed_at', 'delivery_notes.number_items')
            ->defaultSort('-packed_at')
            ->allowedSorts(['packed_at', 'reference', 'customer_name', 'number_items']);
    }

    private function returnsProcessed(Warehouse $warehouse, Carbon $now): QueryBuilder
    {
        return QueryBuilder::for(ReturnDeliveryNoteItem::class)
            ->join('return_delivery_notes', 'return_delivery_notes.id', 'return_delivery_note_items.return_delivery_note_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'return_delivery_note_items.org_stock_id')
            ->where('return_delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('return_delivery_note_items.deleted_at')
            ->where('return_delivery_note_items.processed_at', '>=', $now->copy()->startOfMonth()->utc())
            ->select(
                'return_delivery_note_items.processed_at',
                'return_delivery_note_items.total_item_returned as restocked',
                'return_delivery_note_items.total_item_damaged as damaged',
                'return_delivery_note_items.total_item_not_returned as not_returned',
                'return_delivery_notes.slug as return_delivery_note_slug',
                'return_delivery_notes.reference',
                'org_stocks.code as org_stock_code',
                'org_stocks.slug as org_stock_slug',
            )
            ->defaultSort('-processed_at')
            ->allowedSorts(['processed_at', 'reference', 'org_stock_code', 'restocked', 'damaged', 'not_returned']);
    }

    private function returnReasons(Warehouse $warehouse, Carbon $now, ?string $reason): QueryBuilder
    {
        return QueryBuilder::for(OrderReturn::class)
            ->leftJoin('customers', 'customers.id', 'returns.customer_id')
            ->where('returns.warehouse_id', $warehouse->id)
            ->where('returns.created_at', '>=', $now->copy()->startOfMonth()->utc())
            ->whereRaw("coalesce(trim(returns.return_reason), '') <> ''")
            ->when($reason, fn (Builder $query) => $query->whereRaw('lower(trim(returns.return_reason)) = lower(trim(?))', [$reason]))
            ->select('returns.slug as return_slug', 'returns.reference', 'returns.state', 'returns.return_reason', 'returns.created_at', 'customers.name as customer_name')
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'reference', 'state', 'return_reason', 'customer_name']);
    }

    private function row(array $row, Warehouse $warehouse, string $timezone): array
    {
        $parameters = ['organisation' => $warehouse->organisation->slug, 'warehouse' => $warehouse->slug];

        $route = match (true) {
            isset($row['return_delivery_note_slug']) => ['name' => 'grp.org.warehouses.show.incoming.return_delivery_notes.show', 'parameters' => [...$parameters, 'returnDeliveryNote' => $row['return_delivery_note_slug']]],
            isset($row['return_slug'])               => ['name' => 'grp.org.warehouses.show.incoming.returns.show', 'parameters' => [...$parameters, 'return' => $row['return_slug']]],
            isset($row['reference'])                 => ['name' => 'grp.org.warehouses.show.dispatching.delivery_notes.show', 'parameters' => [...$parameters, 'deliveryNote' => $row['slug']]],
            default                                  => ['name' => 'grp.org.warehouses.show.infrastructure.locations.show', 'parameters' => [...$parameters, 'location' => $row['slug']]],
        };

        foreach (['date', 'dispatched_at', 'packed_at', 'created_at', 'processed_at', 'audited_at'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $row[$column] ? Carbon::parse($row[$column])->setTimezone($timezone)->format('d M Y H:i') : __('Never');
            }
        }

        foreach (['quantity', 'restocked', 'damaged', 'not_returned'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = (float) $row[$column];
            }
        }

        if (array_key_exists('time_to_dispatch', $row)) {
            $row['time_to_dispatch'] = CarbonInterval::seconds((int) $row['time_to_dispatch'])->cascade()->forHumans(['short' => true, 'parts' => 2]);
        }

        if (isset($row['org_stock_slug'])) {
            $row['org_stock_route'] = ['name' => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', 'parameters' => [...$parameters, 'orgStock' => $row['org_stock_slug']]];
        }

        return [...$row, 'route' => $route];
    }

    public function tableStructure(string $list): Closure
    {
        return function (InertiaTable $table) use ($list) {
            $table->withEmptyState(['title' => __('Nothing here right now'), 'count' => 0]);

            $columns = match ($list) {
                'age_under_4h', 'age_4_24h', 'age_1_2d', 'age_over_2d' => ['reference' => __('Delivery note'), 'customer_name' => __('Customer'), 'state' => __('State'), 'date' => __('Reached the warehouse')],
                'dispatched'        => ['reference' => __('Delivery note'), 'customer_name' => __('Customer'), 'date' => __('Reached the warehouse'), 'dispatched_at' => __('Dispatched'), 'time_to_dispatch' => __('Time to dispatch')],
                'not_counted'       => ['code' => __('Location'), 'area' => __('Area'), 'audited_at' => __('Last counted')],
                'picked_today'      => ['created_at' => __('Picked'), 'reference' => __('Delivery note'), 'org_stock_code' => __('SKO'), 'location_code' => __('Location'), 'quantity' => __('Quantity')],
                'short_today'       => ['created_at' => __('Marked'), 'reference' => __('Delivery note'), 'org_stock_code' => __('SKO'), 'location_code' => __('Location'), 'not_picked_reason' => __('Reason')],
                'packed_today'      => ['packed_at' => __('Packed'), 'reference' => __('Delivery note'), 'customer_name' => __('Customer'), 'number_items' => __('Items')],
                'returns_processed' => ['processed_at' => __('Processed'), 'reference' => __('Return'), 'org_stock_code' => __('SKO'), 'restocked' => __('Restocked'), 'damaged' => __('Damaged'), 'not_returned' => __('Not returned')],
                'return_reasons'    => ['created_at' => __('Created'), 'reference' => __('Return'), 'customer_name' => __('Customer'), 'return_reason' => __('Reason'), 'state' => __('State')],
            };

            foreach ($columns as $key => $label) {
                $table->column(key: $key, label: $label, canBeHidden: false, sortable: !in_array($key, ['location_code', 'not_picked_reason']), align: in_array($key, ['quantity', 'number_items', 'restocked', 'damaged', 'not_returned']) ? 'right' : 'left');
            }
        };
    }

    public function title(string $list): string
    {
        return match ($list) {
            'age_under_4h'      => __('Open delivery notes under 4 hours old'),
            'age_4_24h'         => __('Open delivery notes 4 to 24 hours old'),
            'age_1_2d'          => __('Open delivery notes 1 to 2 days old'),
            'age_over_2d'       => __('Open delivery notes over 2 days old'),
            'dispatched'        => __('Time to dispatch'),
            'not_counted'       => __('Locations not counted in 90 days'),
            'picked_today'      => __('Lines picked today'),
            'short_today'       => __('Short picks today'),
            'packed_today'      => __('Delivery notes packed today'),
            'returns_processed' => __('Returns processed this month'),
            'return_reasons'    => __('Return reasons this month'),
        };
    }

    public function htmlResponse(LengthAwarePaginator $records, ActionRequest $request): Response
    {
        $title  = $this->title($this->list);
        $reason = $this->get('reason');

        return Inertia::render(
            'Org/Warehouse/OperationsRecords',
            [
                'breadcrumbs' => ShowWarehouse::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'     => $title,
                    'model'     => $this->warehouse->name,
                    'afterTitle' => $reason ? ['label' => $reason] : null,
                    'icon'      => ['icon' => ['fal', 'fa-truck-loading'], 'title' => __('Operations (In/Out)')],
                ],
                'data'        => JsonResource::collection($records),
            ]
        )->table($this->tableStructure($this->list));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, string $list, ActionRequest $request): LengthAwarePaginator
    {
        $this->list = $list;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $list, $this->validatedData);
    }
}
