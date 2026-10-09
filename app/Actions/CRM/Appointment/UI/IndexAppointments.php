<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\AppointmentType\UI\WithAppointmentsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\UI\CRM\AppointmentsTabsEnum;
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

    public function handle(Shop $shop, AppointmentsTabsEnum $tab): LengthAwarePaginator
    {
        $prefix = $tab->value;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('appointments.contact_name', $value)
                    ->orWhereStartWith('appointments.email', $value)
                    ->orWhereStartWith('appointments.phone', $value);
            });
        });
        InertiaTable::updateQueryBuilderParameters($prefix);

        $queryBuilder = QueryBuilder::for(Appointment::class)
            ->where('appointments.shop_id', $shop->id)
            ->with(['appointmentType', 'visitor', 'user', 'shop.timezone']);

        match ($tab) {
            AppointmentsTabsEnum::REQUESTED => $queryBuilder
                ->where('appointments.state', AppointmentStateEnum::REQUESTED)
                ->where('appointments.ends_at', '>=', now())
                ->defaultSort('appointments.starts_at'),
            AppointmentsTabsEnum::UPCOMING => $queryBuilder
                ->where('appointments.state', AppointmentStateEnum::ACCEPTED)
                ->where('appointments.ends_at', '>=', now())
                ->defaultSort('appointments.starts_at'),
            AppointmentsTabsEnum::PAST => $queryBuilder
                ->whereNotIn('appointments.state', AppointmentStateEnum::closed())
                ->where('appointments.ends_at', '<', now())
                ->defaultSort('-appointments.starts_at'),
            AppointmentsTabsEnum::CANCELLED => $queryBuilder
                ->whereIn('appointments.state', AppointmentStateEnum::closed())
                ->defaultSort('-appointments.starts_at'),
        };

        return $queryBuilder
            ->select('appointments.*')
            ->allowedSorts([
                AllowedSort::field('when', 'appointments.starts_at'),
                AllowedSort::field('contact_name', 'appointments.contact_name'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, AppointmentsTabsEnum $tab): Closure
    {
        return function (InertiaTable $table) use ($shop, $tab) {
            $table->name($tab->value)->pageName($tab->value.'Page');

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => match ($tab) {
                        AppointmentsTabsEnum::REQUESTED => __('No requests waiting'),
                        AppointmentsTabsEnum::UPCOMING  => __('No upcoming appointments'),
                        AppointmentsTabsEnum::PAST      => __('No past appointments'),
                        AppointmentsTabsEnum::CANCELLED => __('No declined or cancelled appointments'),
                    },
                    'description' => $tab === AppointmentsTabsEnum::UPCOMING
                        ? __('Bookings from customers show up here. You can also add one yourself, for example after a phone call.')
                        : null,
                    'count'       => 0,
                    'action'      => $this->canEdit && $tab === AppointmentsTabsEnum::UPCOMING ? [
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
                ->column(key: 'staff_name', label: __('Staff'), canBeHidden: false);

            if ($this->canEdit && in_array($tab, [AppointmentsTabsEnum::REQUESTED, AppointmentsTabsEnum::UPCOMING], true)) {
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

        $tabData = [];
        foreach (AppointmentsTabsEnum::cases() as $tab) {
            $tabData[$tab->value] = $this->tab === $tab->value
                ? fn () => AppointmentsResource::collection($appointments)
                : Inertia::optional(fn () => AppointmentsResource::collection($this->handle($this->shop, $tab)));
        }

        $response = Inertia::render(
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
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AppointmentsTabsEnum::navigation(),
                ],
                ...$tabData,
            ]
        );

        foreach (AppointmentsTabsEnum::cases() as $tab) {
            $response->table($this->tableStructure($this->shop, $tab));
        }

        return $response;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $hasRequests = $shop->appointments()
            ->where('state', AppointmentStateEnum::REQUESTED)
            ->where('ends_at', '>=', now())
            ->exists();

        $this->initialisationFromShop($shop, $request)->withTab(
            AppointmentsTabsEnum::values(),
            $hasRequests ? AppointmentsTabsEnum::REQUESTED->value : AppointmentsTabsEnum::UPCOMING->value
        );

        return $this->handle($shop, AppointmentsTabsEnum::from($this->tab));
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
