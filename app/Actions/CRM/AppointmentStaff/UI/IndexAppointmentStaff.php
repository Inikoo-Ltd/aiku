<?php

namespace App\Actions\CRM\AppointmentStaff\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\AppointmentType\UI\WithAppointmentsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\AppointmentStaffResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexAppointmentStaff extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentsSubNavigation;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('users.contact_name', $value)
                    ->orWhereStartWith('users.username', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(User::class)
            ->join('appointment_type_user', 'appointment_type_user.user_id', '=', 'users.id')
            ->join('appointment_types', 'appointment_types.id', '=', 'appointment_type_user.appointment_type_id')
            ->where('appointment_types.shop_id', $shop->id)
            ->whereNull('appointment_types.deleted_at')
            ->select([
                'users.id',
                'users.username',
                'users.contact_name',
                DB::raw("string_agg(appointment_types.name, ', ' order by appointment_types.name) as appointment_types"),
                DB::raw('count(appointment_types.id) as number_appointment_types'),
            ])
            ->groupBy('users.id')
            ->defaultSort('users.contact_name')
            ->allowedSorts([
                AllowedSort::field('contact_name', 'users.contact_name'),
                AllowedSort::field('username', 'users.username'),
                'number_appointment_types',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No staff arrange appointments yet'),
                    'description' => __('Add the people who meet customers, and the appointments they handle.'),
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('Add staff'),
                        'label'   => __('Staff'),
                        'route'   => [
                            'name'       => 'grp.org.shops.show.crm.appointments.staff.create',
                            'parameters' => [$shop->organisation->slug, $shop->slug],
                        ],
                    ] : null,
                ])
                ->column(key: 'contact_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'username', label: __('Username'), canBeHidden: true, sortable: true, searchable: true)
                ->column(key: 'appointment_types', label: __('Appointments they arrange'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $staff): AnonymousResourceCollection
    {
        return AppointmentStaffResource::collection($staff);
    }

    public function htmlResponse(LengthAwarePaginator $staff, ActionRequest $request): Response
    {
        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('Add staff'),
                'label'   => __('Staff'),
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.appointments.staff.create',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/AppointmentStaff',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Appointment staff'),
                'pageHead'    => [
                    'title'         => __('Staff'),
                    'model'         => __('Appointments'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Staff who arrange appointments'),
                    ],
                    'actions'       => $actions,
                    'subNavigation' => $this->getAppointmentsSubNavigation($this->shop),
                ],
                'data'        => AppointmentStaffResource::collection($staff),
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
                            'name'       => 'grp.org.shops.show.crm.appointments.staff.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'shop'         => $routeParameters['shop'],
                            ],
                        ],
                        'label' => __('Appointment staff'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
