<?php

namespace App\Actions\CRM\AppointmentType\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\AppointmentTypesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexAppointmentTypes extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentsSubNavigation;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('appointment_types.name', $value)
                    ->orWhereAnyWordStartWith('appointment_types.location', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(AppointmentType::class)
            ->where('appointment_types.shop_id', $shop->id)
            ->withCount('attendees')
            ->defaultSort('appointment_types.name')
            ->allowedSorts([
                AllowedSort::field('name', 'appointment_types.name'),
                AllowedSort::field('meeting_mode', 'appointment_types.meeting_mode'),
                AllowedSort::field('duration_minutes', 'appointment_types.duration_minutes'),
                AllowedSort::field('is_active', 'appointment_types.is_active'),
                'attendees_count',
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
                    'title'       => __('No appointment types yet'),
                    'description' => __('Create one to set when customers can book a visit and who attends.'),
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New appointment type'),
                        'label'   => __('Appointment type'),
                        'route'   => [
                            'name'       => 'grp.org.shops.show.crm.appointments.types.create',
                            'parameters' => [$shop->organisation->slug, $shop->slug],
                        ],
                    ] : null,
                ])
                ->column(key: 'is_active', label: '', type: 'icon', canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'meeting_mode', label: __('Visit type'), canBeHidden: false, sortable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: true)
                ->column(key: 'duration_minutes', label: __('Duration'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'attendees_count', label: __('Staff'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $appointmentTypes): AnonymousResourceCollection
    {
        return AppointmentTypesResource::collection($appointmentTypes);
    }

    public function htmlResponse(LengthAwarePaginator $appointmentTypes, ActionRequest $request): Response
    {
        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New appointment type'),
                'label'   => __('Appointment type'),
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.appointments.types.create',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/AppointmentTypes',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Appointment types'),
                'pageHead'    => [
                    'title'   => __('Appointment types'),
                    'model'   => __('Appointments'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-calendar'],
                        'title' => __('Appointment types'),
                    ],
                    'actions'       => $actions,
                    'subNavigation' => $this->getAppointmentsSubNavigation($this->shop),
                ],
                'data'        => AppointmentTypesResource::collection($appointmentTypes),
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
                            'name'       => 'grp.org.shops.show.crm.appointments.types.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'shop'         => $routeParameters['shop'],
                            ],
                        ],
                        'label' => __('Appointment types'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
