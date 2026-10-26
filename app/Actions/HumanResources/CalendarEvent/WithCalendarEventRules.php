<?php

namespace App\Actions\HumanResources\CalendarEvent;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use App\Models\HumanResources\CalendarEvent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait WithCalendarEventRules
{
    protected ?CalendarEvent $calendarEvent = null;

    public static function canEditCalendar(User $user, Organisation $organisation): bool
    {
        return $user->authTo(["calendar.{$organisation->id}.edit", "org-admin.{$organisation->id}"]);
    }

    public static function canSeeShopCustomers(?User $viewer, Organisation $organisation, int $shopId): bool
    {
        return !$viewer || $viewer->authTo(["crm.$shopId.view", "accounting.$organisation->id.view"]);
    }

    public static function calendarTimezone(Organisation $organisation): string
    {
        return $organisation->timezone?->name ?? 'UTC';
    }

    /**
     * The people an event can be with: the active, human users who work in the organisation.
     *
     * @return Collection<int, User>
     */
    public static function participantOptions(Organisation $organisation): Collection
    {
        return User::query()
            ->whereIn(
                'id',
                DB::table('user_has_authorised_models')
                    ->where('model_type', 'Organisation')
                    ->where('model_id', $organisation->id)
                    ->select('user_id')
            )
            ->where('status', true)
            ->where('is_bot', false)
            ->orderByRaw('coalesce(contact_name, username)')
            ->get(['id', 'username', 'contact_name', 'nickname']);
    }

    public function calendarEventRules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'location'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_all_day'     => ['sometimes', 'boolean'],
            'starts_at'      => ['required', 'date_format:Y-m-d H:i'],
            'ends_at'        => ['required', 'date_format:Y-m-d H:i'],
            'participants'   => ['sometimes', 'array'],
            'participants.*' => ['integer', Rule::in(self::participantOptions($this->organisation)->pluck('id')->all())],
            'allow_overlap'  => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        [$startsAt, $endsAt] = $this->eventPeriod($this->get('starts_at'), $this->get('ends_at'), (bool) $this->get('is_all_day', false));

        if ($endsAt->lte($startsAt)) {
            $validator->errors()->add('ends_at', __('The event must end after it starts.'));

            return;
        }

        if ($this->get('allow_overlap')) {
            return;
        }

        $clashes = $this->findClashes($this->organisation, $startsAt, $endsAt, (array) $this->get('participants', []), $this->calendarEvent, auth('web')->user());
        if ($clashes) {
            $validator->errors()->add('overlap', implode("\n", $clashes));
        }
    }

    /**
     * Wall times typed in the organisation's timezone, as UTC. An all-day event takes the
     * whole of its first to its last day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function eventPeriod(string $startsAt, string $endsAt, bool $isAllDay): array
    {
        $timezone = self::calendarTimezone($this->organisation);

        $start = Carbon::createFromFormat('Y-m-d H:i', $startsAt, $timezone);
        $end   = Carbon::createFromFormat('Y-m-d H:i', $endsAt, $timezone);

        if ($isAllDay) {
            $start = $start->startOfDay();
            $end   = $end->startOfDay()->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventAttributes(array $modelData): array
    {
        [$startsAt, $endsAt] = $this->eventPeriod($modelData['starts_at'], $modelData['ends_at'], (bool) ($modelData['is_all_day'] ?? false));

        return array_merge(
            collect($modelData)->only(['title', 'description', 'location', 'is_all_day'])->all(),
            [
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
            ]
        );
    }

    /**
     * What a participant is already doing in that time: other events of the organisation
     * and the shop appointments they look after, naming the customer only to a viewer who
     * may see that shop's customers.
     *
     * @param  array<int, int>  $participantIds
     *
     * @return array<int, string>
     */
    public function findClashes(Organisation $organisation, Carbon $startsAt, Carbon $endsAt, array $participantIds, ?CalendarEvent $ignore = null, ?User $viewer = null): array
    {
        if (!$participantIds) {
            return [];
        }

        $timezone = self::calendarTimezone($organisation);
        $names    = self::participantOptions($organisation)->whereIn('id', $participantIds)->mapWithKeys(fn (User $user) => [$user->id => $user->chatName()]);
        $period   = fn (Carbon $from, Carbon $to) => $from->copy()->setTimezone($timezone)->format('D j M H:i').'–'.$to->copy()->setTimezone($timezone)->format('H:i');

        $clashes = [];

        CalendarEvent::query()
            ->where('organisation_id', $organisation->id)
            ->overlapping($startsAt, $endsAt)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->whereHas('participants', fn ($query) => $query->whereIn('users.id', $participantIds))
            ->with(['participants' => fn ($query) => $query->whereIn('users.id', $participantIds)])
            ->orderBy('starts_at')
            ->get()
            ->each(function (CalendarEvent $event) use (&$clashes, $names, $period) {
                $clashes[] = __(':people: :title (:period)', [
                    'people' => $event->participants->map(fn (User $user) => $names[$user->id] ?? $user->chatName())->join(', '),
                    'title'  => $event->title,
                    'period' => $period($event->starts_at, $event->ends_at),
                ]);
            });

        Appointment::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('user_id', $participantIds)
            ->whereNotIn('state', AppointmentStateEnum::closed())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with(['appointmentType', 'shop'])
            ->orderBy('starts_at')
            ->get()
            ->each(function (Appointment $appointment) use (&$clashes, $names, $period, $viewer, $organisation) {
                $clashes[] = self::canSeeShopCustomers($viewer, $organisation, $appointment->shop_id)
                    ? __(':people: appointment with :contact (:period)', [
                        'people'  => $names[$appointment->user_id] ?? '',
                        'contact' => $appointment->contact_name,
                        'period'  => $period($appointment->starts_at, $appointment->ends_at),
                    ])
                    : __(':people: appointment in :shop (:period)', [
                        'people' => $names[$appointment->user_id] ?? '',
                        'shop'   => $appointment->shop->code,
                        'period' => $period($appointment->starts_at, $appointment->ends_at),
                    ]);
            });

        return $clashes;
    }
}
