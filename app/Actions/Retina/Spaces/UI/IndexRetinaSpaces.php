<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-13h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Retina\Spaces\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\SpacesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaSpaces extends RetinaAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('spaces.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Space::class);
        $query->where('spaces.fulfilment_customer_id', $fulfilmentCustomer->id);


        $query->leftjoin('rentals', 'spaces.rental_id', '=', 'rentals.id');
        $query->leftjoin('currencies', 'rentals.currency_id', '=', 'currencies.id');
        $query->leftjoin('recurring_bills', 'spaces.current_recurring_bill_id', '=', 'recurring_bills.id');

        return $query->defaultSort('spaces.reference')
            ->select([
                'spaces.id',
                'spaces.reference',
                'spaces.slug',
                'spaces.state',
                'spaces.start_at',
                'spaces.end_at',
                'rentals.slug as rental_slug',
                'rentals.name as rental_name',
                'rentals.code as rental_code',
                'rentals.price as rental_price',
                'rentals.unit as rental_unit',
                'currencies.symbol as currency_symbol',
            ])
            ->allowedSorts(['id', 'reference', 'state', 'start_at', 'end_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($fulfilmentCustomer, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }


            $noResults = __("No purges found");

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                    ]
                );


            $table->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false);
            $table->column(key: 'rental', label: __('rental'), canBeHidden: false);
            $table->column(key: 'start_at', label: __('start'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'end_at', label: __('end'), canBeHidden: false, sortable: true, searchable: true);

        };
    }

    public function jsonResponse(LengthAwarePaginator $spaces): AnonymousResourceCollection
    {
        return SpacesResource::collection($spaces);
    }

    public function htmlResponse(LengthAwarePaginator $spaces, ActionRequest $request): Response
    {
        $icon = ['fal', 'fa-parking'];
        $title = __('Spaces');


        return Inertia::render(
            'Space/RetinaSpaces',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => $title,
                'pageHead' => [
                    'title' => $title,
                    'icon' => $icon,
                ],
                'data' => SpacesResource::collection($spaces)
            ]
        )->table(
            $this->tableStructure(
                fulfilmentCustomer: $this->fulfilmentCustomer,
            )
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(fulfilmentCustomer: $this->fulfilmentCustomer);
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.fulfilment.spaces.index',
                            ],
                            'label' => __('Spaces'),
                        ]
                    ]
                ]
            );



    }
}
