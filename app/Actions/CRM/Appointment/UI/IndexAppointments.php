<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Oct 2023 21:20:25 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\AppointmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Appointment;
use App\Models\CRM\Customer;
use App\Models\HumanResources\Employee;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Guest;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IndexAppointments extends OrgAction
{
    private Employee|Customer|Shop|Guest $parent;




    public function handle(Shop|Customer|Employee|Guest $parent, $prefix = null): LengthAwarePaginator
    {
        $this->parent = $parent;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Appointment::class);

        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        return $queryBuilder
            ->defaultSort('-schedule_at')
            ->allowedSorts(['schedule_at'])
            ->when(true, function ($query) use ($parent) {
                if (class_basename($parent) == 'Shop') {
                    $query->where('appointments.shop_id', $parent->id);
                } elseif (class_basename($parent) == 'Customer') {
                    $query->where('appointments.customer_id', $parent->id);
                }
            })
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|Customer|Employee|Guest $parent, ?array $modelOperations = null, $prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $exportLinks) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No appointments'),
                        'count' => 0
                    ]
                );
            if ($exportLinks) {
                $table->withExportLinks($exportLinks);
            }


            $table->column(key: 'name', label: 'appointment name')
                ->column(key: 'customer_name', label: __('customer name'))
                ->column(key: 'schedule_at', label: __('schedule at'), sortable: true)
                ->column(key: 'state', label: __('state'))
                ->column(key: 'type', label: __('type'))
                ->column(key: 'event', label: __('event'))
                ->column(key: 'event_address', label: __('event address'))
                ->defaultSort('published_at');
        };
    }

    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $appointments, ActionRequest $request): Response
    {
        $scope     = $this->parent;
        $container = null;
        if (class_basename($scope) == 'Shop' && $this->organisation->stats->number_shops > 1) {
            $container = [
                'icon'    => ['fal', 'fa-store-alt'],
                'tooltip' => __('Shop'),
                'label'   => Str::possessive($scope->name)
            ];
        }

        return Inertia::render(
            'CRM/Appointments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => __('appointments'),
                'pageHead'    => [
                    'title'     => __('appointments'),
                    'container' => $container,
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-handshake'],
                        'title' => __('appointment')
                    ],
                    'actions'   =>
                        [
                            $this->canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new appointment'),
                                'label'   => __('appointment'),
                                'route'   => [
                                    'name'       => 'org.crm.shop.appointments.create',
                                    'parameters' => array_values($this->originalParameters)
                                ]
                            ] : []
                        ]
                ],
                'data'        => AppointmentResource::collection($appointments),

            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: 'appointments'));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {


        return [];
    }


}
