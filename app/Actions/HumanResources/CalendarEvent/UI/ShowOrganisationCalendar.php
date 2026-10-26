<?php

namespace App\Actions\HumanResources\CalendarEvent\UI;

use App\Actions\HumanResources\CalendarEvent\StoreCalendarEvent;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentBlock;
use App\Models\HumanResources\CalendarEvent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationCalendar extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = StoreCalendarEvent::canEditCalendar($request->user(), $this->organisation);

        return true;
    }

    /**
     * One month as whole weeks, Monday first, in the organisation's timezone: the events of the
     * organisation, each on every day it covers, the open appointments of all its shops and the times
     * those shops blocked for bookings.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     previous: string,
     *     next: string,
     *     current: string,
     *     now: string,
     *     timezone: string,
     *     weekdays: array<int, string>,
     *     total: int,
     *     weeks: array<int, array<int, array{date: string, day: int, in_month: bool, is_today: bool, events: array<int, array<string, mixed>>, appointments: array<int, array<string, mixed>>, blocks: array<int, array<string, mixed>>}>>
     * }
     */
    public function handle(Organisation $organisation, User $viewer, ?string $month = null): array
    {
        $timezone   = StoreCalendarEvent::calendarTimezone($organisation);
        $today      = now($timezone)->startOfDay();
        $monthStart = $month ? Carbon::createFromFormat('!Y-m', $month, $timezone) : $today->copy()->startOfMonth();
        $firstDay   = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastDay    = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $rangeStart = $firstDay->copy()->utc();
        $rangeEnd   = $lastDay->copy()->endOfDay()->utc();

        $events = $organisation->calendarEvents()
            ->overlapping($rangeStart, $rangeEnd)
            ->with(['participants', 'createdBy'])
            ->orderBy('starts_at')
            ->get();

        $appointments = Appointment::query()
            ->where('organisation_id', $organisation->id)
            ->whereNotIn('state', AppointmentStateEnum::closed())
            ->where('starts_at', '>=', $rangeStart)
            ->where('starts_at', '<=', $rangeEnd)
            ->with(['appointmentType', 'user', 'shop'])
            ->orderBy('starts_at')
            ->get();

        $blocks = AppointmentBlock::query()
            ->where('organisation_id', $organisation->id)
            ->overlapping($rangeStart, $rangeEnd)
            ->with(['appointmentType', 'shop'])
            ->orderBy('starts_at')
            ->get();

        $viewableShopIds = $appointments->pluck('shop_id')->unique()
            ->filter(fn (int $shopId) => StoreCalendarEvent::canSeeShopCustomers($viewer, $organisation, $shopId))
            ->values()
            ->all();

        $appointmentsByDay = $appointments->groupBy(fn (Appointment $appointment) => $appointment->starts_at->copy()->setTimezone($timezone)->toDateString());

        $weeks = [];
        for ($day = $firstDay->copy(), $position = 0; $day->lte($lastDay); $day->addDay(), $position++) {
            $dayStart = $day->copy()->utc();
            $dayEnd   = $day->copy()->addDay()->utc();

            $weeks[intdiv($position, 7)][] = [
                'date'         => $day->toDateString(),
                'day'          => $day->day,
                'in_month'     => $day->isSameMonth($monthStart),
                'is_today'     => $day->isSameDay($today),
                'events'       => $events
                    ->filter(fn (CalendarEvent $event) => $event->starts_at->lt($dayEnd) && $event->ends_at->gt($dayStart))
                    ->map(fn (CalendarEvent $event) => $this->eventData($event, $timezone, $dayStart))
                    ->values()
                    ->all(),
                'appointments' => $appointmentsByDay->get($day->toDateString(), collect())
                    ->map(fn (Appointment $appointment) => [
                        'id'                    => $appointment->id,
                        'time'                  => $appointment->starts_at->copy()->setTimezone($timezone)->format('H:i'),
                        'end_time'              => $appointment->ends_at->copy()->setTimezone($timezone)->format('H:i'),
                        'contact_name'          => in_array($appointment->shop_id, $viewableShopIds) ? $appointment->contact_name : null,
                        'appointment_type_name' => $appointment->appointmentType?->name,
                        'staff_name'            => $appointment->user?->contact_name,
                        'shop_code'             => $appointment->shop->code,
                        'state'                 => $appointment->state->value,
                        'state_label'           => $appointment->state->label(),
                        'url'                   => in_array($appointment->shop_id, $viewableShopIds)
                            ? route('grp.org.shops.show.crm.appointments.show', [$organisation->slug, $appointment->shop->slug, $appointment->id])
                            : null,
                    ])
                    ->values()
                    ->all(),
                'blocks'       => $blocks
                    ->filter(fn (AppointmentBlock $block) => $block->starts_at->lt($dayEnd) && $block->ends_at->gt($dayStart))
                    ->map(fn (AppointmentBlock $block) => [
                        'id'                    => $block->id,
                        'is_all_day'            => $block->is_all_day,
                        'time'                  => $block->is_all_day || $block->starts_at->lt($dayStart) ? null : $block->starts_at->copy()->setTimezone($timezone)->format('H:i'),
                        'end_time'              => $block->is_all_day || $block->ends_at->gt($dayEnd) ? null : $block->ends_at->copy()->setTimezone($timezone)->format('H:i'),
                        'reason'                => $block->reason,
                        'appointment_type_name' => $block->appointmentType?->name,
                        'shop_code'             => $block->shop->code,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [
            'month'    => $monthStart->format('Y-m'),
            'label'    => $monthStart->translatedFormat('F Y'),
            'previous' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
            'next'     => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
            'current'  => $today->format('Y-m'),
            'now'      => now($timezone)->format('Y-m-d H:i'),
            'timezone' => $timezone,
            'weekdays' => collect(range(0, 6))->map(fn (int $offset) => $firstDay->copy()->addDays($offset)->translatedFormat('D'))->all(),
            'total'    => $events->filter(fn (CalendarEvent $event) => $event->starts_at->copy()->setTimezone($timezone)->isSameMonth($monthStart))->count()
                + $appointments->filter(fn (Appointment $appointment) => $appointment->starts_at->copy()->setTimezone($timezone)->isSameMonth($monthStart))->count(),
            'weeks'    => $weeks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventData(CalendarEvent $event, string $timezone, Carbon $dayStart): array
    {
        $startsAt     = $event->starts_at->copy()->setTimezone($timezone);
        $endsAt       = $event->ends_at->copy()->setTimezone($timezone);
        $lastDay      = $event->is_all_day ? $endsAt->copy()->subDay() : $endsAt;
        $startsOnThisDay = $event->starts_at->gte($dayStart);

        return [
            'id'              => $event->id,
            'title'           => $event->title,
            'description'     => $event->description,
            'location'        => $event->location,
            'is_all_day'      => $event->is_all_day,
            'time'            => $event->is_all_day || !$startsOnThisDay ? null : $startsAt->format('H:i'),
            'starts_at'       => $startsAt->format('Y-m-d H:i'),
            'ends_at'         => $lastDay->format('Y-m-d H:i'),
            'participants'    => $event->participants->map(fn (User $user) => [
                'id'   => $user->id,
                'name' => $user->chatName(),
            ])->values()->all(),
            'created_by_name' => $event->createdBy?->chatName(),
        ];
    }

    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'nullable', 'date_format:Y-m'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request->user(), $this->validatedData['month'] ?? null);
    }

    public function htmlResponse(array $calendar, ActionRequest $request): Response
    {
        $title = __('Calendar');

        return Inertia::render('Org/Calendar/OrganisationCalendar', [
            'title'               => $title,
            'breadcrumbs'         => $this->getBreadcrumbs($this->organisation),
            'pageHead'            => [
                'title' => $title,
                'icon'  => [
                    'icon'  => ['fal', 'fa-calendar-alt'],
                    'title' => $title,
                ],
                'model' => $this->organisation->name,
            ],
            'calendar'            => $calendar,
            'can_edit'            => $this->canEdit,
            'participant_options' => StoreCalendarEvent::participantOptions($this->organisation)
                ->map(fn (User $user) => [
                    'id'   => $user->id,
                    'name' => $user->chatName(),
                ])
                ->values()
                ->all(),
            'routes'              => [
                'calendar' => [
                    'name'       => 'grp.org.calendar.index',
                    'parameters' => ['organisation' => $this->organisation->slug],
                ],
                'store'    => [
                    'name'       => 'grp.models.org.calendar_event.store',
                    'parameters' => ['organisation' => $this->organisation->id],
                ],
                'update'   => [
                    'name'       => 'grp.models.calendar_event.update',
                    'parameters' => [],
                ],
                'delete'   => [
                    'name'       => 'grp.models.calendar_event.delete',
                    'parameters' => [],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs(Organisation $organisation): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-calendar-alt',
                        'route' => [
                            'name'       => 'grp.org.calendar.index',
                            'parameters' => ['organisation' => $organisation->slug],
                        ],
                        'label' => __('Calendar'),
                    ],
                ],
            ]
        );
    }
}
