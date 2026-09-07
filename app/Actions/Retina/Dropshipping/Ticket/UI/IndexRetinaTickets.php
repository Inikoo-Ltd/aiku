<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Ticket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\TicketResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Helpers\Ticket;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaTickets extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('tickets.reference ILIKE ?', ["%$value%"])
                    ->orWhereRaw('tickets.subject ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Ticket::class)
            ->where('tickets.customer_id', $customer->id)
            ->defaultSort('-tickets.updated_at')
            ->allowedSorts(['reference', 'subject', 'status', 'created_at', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('ticket'), __('tickets')])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'updated_at', label: __('Updated'), canBeHidden: false, sortable: true, type: 'date')
                ->defaultSort('-updated_at');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function htmlResponse(LengthAwarePaginator $tickets): Response
    {
        return Inertia::render(
            'Dropshipping/RetinaTickets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Support'),
                'pageHead'    => [
                    'title'   => __('Support tickets'),
                    'icon'    => 'fal fa-life-ring',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => ['name' => 'retina.dropshipping.tickets.create'],
                        ],
                    ],
                ],
                'data'        => TicketResource::collection($tickets),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'retina.dropshipping.tickets.index'],
                        'label' => __('Support'),
                    ],
                ],
            ]
        );
    }
}
