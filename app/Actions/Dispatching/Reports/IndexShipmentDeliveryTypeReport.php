<?php

namespace App\Actions\Dispatching\Reports;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithOrgReportsAuthorisation;
use App\Actions\UI\Reports\IndexReports;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\Shipment\ShipmentDeliveryTypeEnum;
use App\Http\Resources\Dispatching\Reports\ShipmentDeliveryTypeReportResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShipmentDeliveryTypeReport extends OrgAction
{
    use WithOrgReportsAuthorisation;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.reference', $value);
            });
        });

        $home        = ShipmentDeliveryTypeEnum::HOME->value;
        $pickupPoint = ShipmentDeliveryTypeEnum::PICKUP_POINT->value;

        return QueryBuilder::for(Shipment::class)
            ->join('customers', 'customers.id', '=', 'shipments.customer_id')
            ->join('shops', 'shops.id', '=', 'shipments.shop_id')
            ->where('shipments.organisation_id', $organisation->id)
            ->where('shops.type', ShopTypeEnum::DROPSHIPPING->value)
            ->whereNull('shipments.deleted_at')
            ->select(
                'customers.id',
                'customers.slug',
                'customers.reference',
                'customers.name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                DB::raw('count(shipments.id) as shipments'),
                DB::raw('coalesce(sum(shipments.number_parcels), 0) as parcels'),
                DB::raw("count(shipments.id) filter (where shipments.delivery_type = '$home') as home"),
                DB::raw("count(shipments.id) filter (where shipments.delivery_type = '$pickupPoint') as pickup_point"),
                DB::raw('count(shipments.id) filter (where shipments.delivery_type is null) as not_classified'),
            )
            ->groupBy('customers.id', 'shops.id')
            ->allowedSorts(['name', 'reference', 'shipments', 'parcels', 'home', 'pickup_point', 'not_classified'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['created_at'])
            ->defaultSort('-shipments')
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No dropshipping shipments'),
                    'description' => __('No shipments found for the selected period.'),
                ])
                ->betweenDates(['created_at']);

            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false);
            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true);
            $table->column(key: 'name', label: __('Customer'), canBeHidden: false, sortable: true);
            $table->column(key: 'shipments', label: __('Shipments'), sortable: true, type: 'number');
            $table->column(key: 'parcels', label: __('Parcels'), sortable: true, type: 'number');
            $table->column(key: 'home', label: __('Home delivery'), sortable: true, type: 'number');
            $table->column(key: 'pickup_point', label: __('Pickup point / locker'), sortable: true, type: 'number');
            $table->column(key: 'not_classified', label: __('Not recorded'), tooltip: __('Shipments created before delivery type was recorded'), sortable: true, type: 'number');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $shipments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Reports/ShipmentDeliveryTypeReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Home vs pickup point'),
                'pageHead'    => [
                    'title' => __('Home vs pickup point'),
                    'icon'  => [
                        'title' => __('Dropshipping shipments by delivery type'),
                        'icon'  => 'fal fa-mailbox'
                    ],
                ],
                'data'        => ShipmentDeliveryTypeReportResource::collection($shipments),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-mailbox',
                        'label' => __('Home vs pickup point'),
                        'route' => [
                            'name'       => 'grp.org.reports.shipment-delivery-type',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
