<?php

namespace App\Actions\Retina\Dropshipping\DeliveryNotes\UI;

use App\Actions\RetinaAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Carbon\Carbon;

class IndexRetinaDropshippingPackingList extends RetinaAction
{
    public function handle(Customer $customer, ?string $startDate = null, ?string $endDate = null, $prefix = null): LengthAwarePaginator
    {
        try {
            if ($startDate) {
                $startDate = Carbon::parse($startDate)->toDateString();
            }
            if ($endDate) {
                $endDate = Carbon::parse($endDate)->toDateString();
            }
        } catch (\Exception $e) {
            $startDate = null;
            $endDate   = null;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('reference', 'LIKE', '%' . $value . '%');
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(DeliveryNote::class);
        $queryBuilder->where('delivery_notes.customer_id', $customer->id);

        if ($startDate && $endDate) {
            $queryBuilder->whereBetween('delivery_notes.date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        } elseif ($startDate) {
            $queryBuilder->whereDate('delivery_notes.date', Carbon::parse($startDate)->toDateString());
        }

        $queryBuilder->defaultSort('-delivery_notes.date')
            ->select([
                'delivery_notes.id',
                'delivery_notes.slug',
                'delivery_notes.reference',
                'delivery_notes.date',
                'delivery_notes.state',
                'delivery_notes.type',
                'delivery_notes.created_at',
                'delivery_notes.updated_at',
                'customer_sales_channels.id as customer_sales_channel_id',
                'customer_sales_channels.reference as customer_sales_channel_reference',
                'customer_sales_channels.slug as customer_sales_channel_slug',
                'customer_sales_channels.name as customer_sales_channel_name',
                'platforms.id as platform_id',
                'platforms.name as platform_name',
                'platforms.code as platform_code',
            ])
            ->leftJoin('customer_sales_channels', 'customer_sales_channels.id', 'delivery_notes.customer_sales_channel_id')
            ->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id');

        return $queryBuilder->allowedSorts(['reference', 'date', 'state', 'customer_sales_channel_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $customer) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No packing lists found"),
                    ]
                );

            $table->column(key: 'type', label: '', canBeHidden: false, sortable: false, searchable: false, type: 'icon')
                  ->defaultSort('reference');
            $table->column(key: 'reference', label: __('Reference'), sortable: true, searchable: true);
            $table->column(key: 'customer_sales_channel_name', label: __('Channel'), sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), sortable: true, searchable: true, align: 'right');
            $table->column(key: 'state', label: __('State'), sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $deliveryNotes): AnonymousResourceCollection
    {
        return JsonResource::collection($deliveryNotes);
    }

    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        $title = __('Packing Lists');

        return Inertia::render(
            'Dropshipping/DeliveryNotes/Index',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-box-open'],
                        'title' => $title
                    ],
                ],
                'data'        => JsonResource::collection($deliveryNotes),
            ]
        )->table($this->tableStructure($this->customer));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(
            $this->customer,
            $request->query('startDate'),
            $request->query('endDate')
        );
    }

    public function getBreadcrumbs(): array
    {
        return [
            ['label' => __('Dashboard'), 'url' => route('retina.dashboard.show')],
            ['label' => __('Packing Lists')],
        ];
    }
}
