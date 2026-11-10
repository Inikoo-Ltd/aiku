<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\AppointmentType\UI\WithAppointmentsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\CRM\Appointment\AppointmentListFilterEnum;
use App\Http\Resources\CRM\AppointmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexAppointments extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentsSubNavigation;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        $now = now();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('appointments.contact_name', $value)
                    ->orWhereStartWith('appointments.email', $value)
                    ->orWhereStartWith('appointments.phone', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Appointment::class)
            ->where('appointments.shop_id', $shop->id)
            ->with([
                'appointmentType',
                'visitor',
                'user',
                'createdBy',
                'shop.timezone',
                'audits' => fn ($query) => $query->where('event', 'updated')->with('user')->orderBy('id'),
            ]);

        $queryBuilder->whereElementGroup(
            key: 'state',
            allowedElements: AppointmentListFilterEnum::values(),
            engine: function ($query, array $elements) use ($now) {
                $query->where(function ($query) use ($elements, $now) {
                    foreach ($elements as $element) {
                        $query->orWhere(fn ($query) => AppointmentListFilterEnum::from($element)->constrain($query, $now));
                    }
                });
            }
        );

        return $queryBuilder
            ->defaultSort('-appointments.starts_at')
            ->select('appointments.*')
            ->allowedSorts([
                AllowedSort::field('when', 'appointments.starts_at'),
                AllowedSort::field('contact_name', 'appointments.contact_name'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public function stateElements(Shop $shop): array
    {
        $now      = now();
        $elements = [];
        foreach (AppointmentListFilterEnum::cases() as $filter) {
            $query = $shop->appointments()->getQuery();
            $filter->constrain($query, $now);
            $elements[$filter->value] = [$filter->label(), $query->count()];
        }

        return $elements;
    }

    public function tableStructure(Shop $shop): Closure
    {
        return function (InertiaTable $table) use ($shop) {
            $table
                ->withGlobalSearch()
                ->elementGroup(
                    key: 'state',
                    label: __('State'),
                    elements: $this->stateElements($shop)
                )
                ->withEmptyState([
                    'title'       => __('No appointments'),
                    'description' => __('Bookings from customers show up here. You can also add one yourself, for example after a phone call.'),
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New appointment'),
                        'label'   => __('Appointment'),
                        'route'   => [
                            'name'       => 'grp.org.shops.show.crm.appointments.create',
                            'parameters' => [$shop->organisation->slug, $shop->slug],
                        ],
                    ] : null,
                ])
                ->column(key: 'state', label: '', type: 'icon', canBeHidden: false)
                ->column(key: 'when', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'appointment_type_name', label: __('Type'), canBeHidden: false)
                ->column(key: 'contact_name', label: __('Visitor'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_visitors', label: __('People'), canBeHidden: true, align: 'right')
                ->column(key: 'staff_name', label: __('Staff'), canBeHidden: false)
                ->column(key: 'status', label: __('Status'), canBeHidden: false);

            if ($this->canEdit) {
                $table->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $appointments, ActionRequest $request): Response
    {
        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New appointment'),
                'label'   => __('Appointment'),
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.appointments.create',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Appointments',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Appointments'),
                'pageHead'    => [
                    'title'         => __('Appointments'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-calendar'],
                        'title' => __('Appointments'),
                    ],
                    'actions'       => $actions,
                    'subNavigation' => $this->getAppointmentsSubNavigation($this->shop),
                ],
                'canEdit'     => $this->canEdit,
                'data'        => AppointmentsResource::collection($appointments),
            ]
        )->table($this->tableStructure($this->shop));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'shop'         => $routeParameters['shop'],
                            ],
                        ],
                        'label' => __('Appointments'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
