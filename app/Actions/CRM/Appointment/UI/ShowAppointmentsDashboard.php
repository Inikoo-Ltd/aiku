<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\CRM\Appointment\Widget\AppointmentWidgetKey;
use App\Actions\CRM\AppointmentType\UI\WithAppointmentsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentListFilterEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Http\Resources\CRM\AppointmentsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAppointmentsDashboard extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentsSubNavigation;

    private const int DAYS_AHEAD = 14;

    private const int NEXT_APPOINTMENTS = 5;

    /**
     * @return array{
     *     total: int,
     *     people_expected: int,
     *     stats: array<int, array<string, mixed>>,
     *     states: array<int, array<string, mixed>>,
     *     types: array<int, array{name: string, total: int, upcoming: int}>,
     *     staff: array<int, array{name: string, total: int, upcoming: int}>,
     *     sources: array<int, array{label: string, total: int}>,
     *     days: array<int, array{date: string, weekday: string, day: string, count: int, is_today: bool}>,
     *     next: array<int, array<string, mixed>>
     * }
     */
    public function handle(Shop $shop): array
    {
        $timezone   = $shop->timezone?->name ?? 'UTC';
        $now        = now();
        $todayStart = now($timezone)->startOfDay();
        $today      = $todayStart->copy()->utc();
        $tomorrow   = $todayStart->copy()->addDay()->utc();
        $inAWeek    = $todayStart->copy()->addDays(7)->utc();
        $windowEnd  = $todayStart->copy()->addDays(self::DAYS_AHEAD)->utc();

        $requested = AppointmentStateEnum::REQUESTED->value;
        $accepted  = AppointmentStateEnum::ACCEPTED->value;
        $declined  = AppointmentStateEnum::DECLINED->value;
        $cancelled = AppointmentStateEnum::CANCELLED->value;

        $counts = $shop->appointments()->toBase()->selectRaw(
            'count(*) as total,
            count(*) filter (where state = ? and ends_at >= ?) as requested,
            count(*) filter (where state = ? and ends_at >= ?) as upcoming,
            count(*) filter (where state in (?, ?) and starts_at >= ? and starts_at < ?) as today,
            count(*) filter (where state in (?, ?) and ends_at >= ? and starts_at < ?) as next_seven_days,
            count(*) filter (where state in (?, ?) or (state in (?, ?) and ends_at < ?)) as past,
            count(*) filter (where state = ?) as declined,
            count(*) filter (where state = ?) as cancelled,
            coalesce(sum(number_visitors) filter (where state in (?, ?) and ends_at >= ?), 0) as people_expected',
            [
                $requested, $now,
                $accepted, $now,
                $requested, $accepted, $today, $tomorrow,
                $requested, $accepted, $now, $inAWeek,
                AppointmentStateEnum::COMPLETED->value, AppointmentStateEnum::NO_SHOW->value, $requested, $accepted, $now,
                $declined,
                $cancelled,
                $requested, $accepted, $now,
            ]
        )->first();

        $upcomingFilter   = 'count(*) filter (where appointments.state in (?, ?) and appointments.ends_at >= ?) as upcoming';
        $upcomingBindings = [$requested, $accepted, $now];

        $types = $shop->appointments()->toBase()
            ->join('appointment_types', 'appointment_types.id', '=', 'appointments.appointment_type_id')
            ->selectRaw('appointment_types.name, count(*) as total, '.$upcomingFilter, $upcomingBindings)
            ->groupBy('appointment_types.id', 'appointment_types.name')
            ->orderByDesc('total')
            ->orderBy('appointment_types.name')
            ->get();

        $staffRows = $shop->appointments()->toBase()
            ->selectRaw('appointments.user_id, count(*) as total, '.$upcomingFilter, $upcomingBindings)
            ->groupBy('appointments.user_id')
            ->orderByDesc('upcoming')
            ->orderByDesc('total')
            ->get();
        $staffUsers = User::whereIn('id', $staffRows->pluck('user_id')->filter())->get()->keyBy('id');

        $sourceCounts = $shop->appointments()->toBase()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $perDay = $shop->appointments()
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->where('starts_at', '>=', $today)
            ->where('starts_at', '<', $windowEnd)
            ->pluck('starts_at')
            ->countBy(fn (Carbon $startsAt) => $startsAt->copy()->setTimezone($timezone)->toDateString());

        $days = [];
        for ($offset = 0; $offset < self::DAYS_AHEAD; $offset++) {
            $day    = $todayStart->copy()->addDays($offset);
            $days[] = [
                'date'     => $day->toDateString(),
                'weekday'  => $day->translatedFormat('D'),
                'day'      => $day->translatedFormat('j M'),
                'count'    => (int) $perDay->get($day->toDateString(), 0),
                'is_today' => $offset === 0,
            ];
        }

        $next = $shop->appointments()
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->where('ends_at', '>=', $now)
            ->with([
                'appointmentType',
                'visitor',
                'user',
                'createdBy',
                'shop.timezone',
                'audits' => fn ($query) => $query->where('event', 'updated')->with('user')->orderBy('id'),
            ])
            ->orderBy('starts_at')
            ->limit(self::NEXT_APPOINTMENTS)
            ->get();

        return [
            'total'           => (int) $counts->total,
            'people_expected' => (int) $counts->people_expected,
            'stats'           => [
                [
                    'key'        => 'requested',
                    'label'      => __('Waiting for your answer'),
                    'value'      => (int) $counts->requested,
                    'icon'       => 'fal fa-hourglass-half',
                    'icon_class' => 'text-amber-500',
                    'route'      => $this->getListRoute($shop, AppointmentListFilterEnum::REQUESTED),
                ],
                [
                    'key'        => 'today',
                    'label'      => __('Today'),
                    'value'      => (int) $counts->today,
                    'icon'       => 'fal fa-calendar-day',
                    'icon_class' => 'text-gray-400',
                    'route'      => $this->getListRoute($shop, AppointmentListFilterEnum::UPCOMING),
                ],
                [
                    'key'        => 'next_seven_days',
                    'label'      => __('Next 7 days'),
                    'value'      => (int) $counts->next_seven_days,
                    'icon'       => 'fal fa-calendar-week',
                    'icon_class' => 'text-gray-400',
                    'route'      => $this->getListRoute($shop, AppointmentListFilterEnum::UPCOMING),
                ],
                [
                    'key'        => 'upcoming',
                    'label'      => __('Accepted and upcoming'),
                    'value'      => (int) $counts->upcoming,
                    'icon'       => 'fal fa-calendar-check',
                    'icon_class' => 'text-green-500',
                    'route'      => $this->getListRoute($shop, AppointmentListFilterEnum::UPCOMING),
                ],
            ],
            'states'          => [
                [
                    'label' => __('Requested'),
                    'value' => (int) $counts->requested,
                    'icon'  => AppointmentStateEnum::REQUESTED->icon(),
                    'route' => $this->getListRoute($shop, AppointmentListFilterEnum::REQUESTED),
                ],
                [
                    'label' => __('Upcoming'),
                    'value' => (int) $counts->upcoming,
                    'icon'  => AppointmentStateEnum::ACCEPTED->icon(),
                    'route' => $this->getListRoute($shop, AppointmentListFilterEnum::UPCOMING),
                ],
                [
                    'label' => __('Past'),
                    'value' => (int) $counts->past,
                    'icon'  => ['icon' => 'fal fa-history', 'tooltip' => __('Past'), 'class' => 'text-gray-400'],
                    'route' => $this->getListRoute($shop, AppointmentListFilterEnum::PAST),
                ],
                [
                    'label' => __('Declined'),
                    'value' => (int) $counts->declined,
                    'icon'  => AppointmentStateEnum::DECLINED->icon(),
                    'route' => $this->getListRoute($shop, AppointmentListFilterEnum::DECLINED),
                ],
                [
                    'label' => __('Cancelled'),
                    'value' => (int) $counts->cancelled,
                    'icon'  => AppointmentStateEnum::CANCELLED->icon(),
                    'route' => $this->getListRoute($shop, AppointmentListFilterEnum::CANCELLED),
                ],
            ],
            'types'           => $types->map(fn (object $type) => [
                'name'     => $type->name,
                'total'    => (int) $type->total,
                'upcoming' => (int) $type->upcoming,
            ])->all(),
            'staff'           => $staffRows->map(fn (object $row) => [
                'name'     => $staffUsers->get($row->user_id)?->chatName() ?? __('Not assigned'),
                'total'    => (int) $row->total,
                'upcoming' => (int) $row->upcoming,
            ])->all(),
            'sources'         => array_map(fn (AppointmentSourceEnum $source) => [
                'label' => $source->label(),
                'total' => (int) $sourceCounts->get($source->value, 0),
            ], AppointmentSourceEnum::cases()),
            'days'            => $days,
            'next'            => AppointmentsResource::collection($next)->resolve(),
        ];
    }

    /**
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function getListRoute(Shop $shop, AppointmentListFilterEnum $filter): array
    {
        return [
            'name'       => 'grp.org.shops.show.crm.appointments.index',
            'parameters' => [
                'organisation' => $shop->organisation->slug,
                'shop'         => $shop->slug,
                'elements'     => ['state' => $filter->value],
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(array $dashboard, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New appointment'),
                'label'   => __('Appointment'),
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.appointments.create',
                    'parameters' => $routeParameters,
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/AppointmentsDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'title'       => __('Appointments dashboard'),
                'pageHead'    => [
                    'title'         => __('Appointments dashboard'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-tachometer-alt'],
                        'title' => __('Appointments dashboard'),
                    ],
                    'actions'       => $actions,
                    'subNavigation' => $this->getAppointmentsSubNavigation($this->shop),
                ],
                'dashboard'   => $dashboard,
                'widget'      => $this->getWidget($this->shop, $request),
            ]
        );
    }

    /**
     * @return array{enabled: bool, embed_code: string|null, can_toggle: bool, update_route: array{name: string, parameters: array<string, int>}}
     */
    private function getWidget(Shop $shop, ActionRequest $request): array
    {
        $key       = Arr::get($shop->settings, 'appointments.widget_key');
        $isEnabled = (bool) Arr::get($shop->settings, 'appointments.widget_enabled', false) && is_string($key) && $key !== '';

        return [
            'enabled'      => $isEnabled,
            'embed_code'   => $isEnabled ? AppointmentWidgetKey::make()->embedSnippet($key) : null,
            'can_toggle'   => $request->user()->authTo(['org-admin.'.$shop->organisation_id, 'shop-admin.'.$shop->id]),
            'update_route' => [
                'name'       => 'grp.models.org.shop.update',
                'parameters' => [
                    'organisation' => $shop->organisation_id,
                    'shop'         => $shop->id,
                ],
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.dashboard',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'shop'         => $routeParameters['shop'],
                            ],
                        ],
                        'label' => __('Dashboard'),
                    ],
                ],
            ]
        );
    }
}
