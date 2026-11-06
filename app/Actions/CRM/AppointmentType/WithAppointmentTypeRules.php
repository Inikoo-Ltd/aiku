<?php

namespace App\Actions\CRM\AppointmentType;

use App\Actions\CRM\Appointment\Zoom\ZoomClient;
use App\Enums\CRM\AppointmentType\AppointmentTypeMediaEnum;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\CRM\AppointmentType;
use App\Rules\IUnique;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait WithAppointmentTypeRules
{
    protected function appointmentTypeRules(bool $isUpdate, ?AppointmentType $appointmentType = null): array
    {
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $optional = $isUpdate ? ['sometimes'] : ['nullable'];
        $time     = ['required', 'date_format:H:i'];

        $uniqueNameConditions = [
            ['column' => 'shop_id', 'value' => $this->shop->id],
            ['column' => 'deleted_at', 'operator' => 'null'],
        ];
        if ($appointmentType) {
            $uniqueNameConditions[] = ['column' => 'id', 'operator' => '!=', 'value' => $appointmentType->id];
        }

        return [
            'name'                          => [...$required, 'string', 'max:255', new IUnique(table: 'appointment_types', extraConditions: $uniqueNameConditions)],
            'description'                   => [...$optional, 'nullable', 'string', 'max:5000'],
            'meeting_mode'                  => [...$required, Rule::enum(AppointmentTypeMeetingModeEnum::class)],
            'location'                      => [...$optional, 'nullable', 'string', 'max:255', $this->belongsToMeetingMode(AppointmentTypeMeetingModeEnum::STORE_VISIT, $appointmentType)],
            'media'                         => [...$optional, 'nullable', Rule::enum(AppointmentTypeMediaEnum::class), $this->belongsToMeetingMode(AppointmentTypeMeetingModeEnum::VIDEO_CALL, $appointmentType), $this->zoomIsConfigured(), $this->googleCalendarIsConnected()],
            'duration_minutes'              => [...$required, 'integer', 'min:5', 'max:480'],
            'buffer_minutes'                => [...$optional, 'integer', 'min:0', 'max:240'],
            'min_notice_hours'              => [...$optional, 'integer', 'min:0', 'max:720'],
            'booking_window_days'           => [...$optional, 'integer', 'min:1', 'max:365'],
            'capacity_per_slot'             => [...$optional, 'integer', 'min:1', 'max:100'],
            'is_active'                     => [...$optional, 'boolean'],
            'start_times'                   => [...$optional, 'nullable', 'array', 'max:48'],
            'start_times.*'                 => ['required', 'date_format:H:i', 'distinct'],
            'availability'                  => [...$optional, 'array'],
            'availability.weekly'           => ['sometimes', 'array'],
            'availability.weekly.*'         => ['array'],
            'availability.weekly.*.*.from'  => $time,
            'availability.weekly.*.*.to'    => $time,
            'availability.dates'            => ['sometimes', 'array', 'max:366'],
            'availability.dates.*.date'     => ['required', 'date_format:Y-m-d', 'distinct'],
            'availability.dates.*.hours'    => ['present', 'array'],
            'availability.dates.*.hours.*.from' => $time,
            'availability.dates.*.hours.*.to'   => $time,
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isEmpty() && $error = $this->availabilityError()) {
            $validator->errors()->add('availability', $error);
        }
    }

    private function availabilityError(): ?string
    {
        $errors = [];

        foreach ((array) $this->get('availability.weekly', []) as $dayOfWeek => $ranges) {
            $errors[] = in_array((int) $dayOfWeek, range(1, 7), true)
                ? $this->hoursRangesError((array) $ranges)
                : __('Unknown day of the week.');
        }

        foreach ((array) $this->get('availability.dates', []) as $date) {
            $error    = $this->hoursRangesError((array) Arr::get($date, 'hours', []));
            $errors[] = $error ? Arr::get($date, 'date').': '.$error : null;
        }

        return collect($errors)->filter()->first();
    }

    private function hoursRangesError(array $ranges): ?string
    {
        $previousTo = null;
        foreach ($this->sortedRanges($ranges) as $range) {
            if ($range['from'] >= $range['to']) {
                return __('Each opening must end after it starts.');
            }
            if ($previousTo && $range['from'] < $previousTo) {
                return __('Openings on the same day cannot overlap.');
            }
            $previousTo = $range['to'];
        }

        return null;
    }

    /**
     * @return array<int, array{from: string, to: string}>
     */
    private function sortedRanges(array $ranges): array
    {
        return collect($ranges)
            ->map(fn ($range) => ['from' => (string) Arr::get($range, 'from'), 'to' => (string) Arr::get($range, 'to')])
            ->sortBy('from')
            ->values()
            ->all();
    }

    /**
     * On the edit page every field saves on its own, so a location or media can arrive while the
     * saved visit type is still the other one. Saving it would silently drop it.
     */
    private function zoomIsConfigured(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === AppointmentTypeMediaEnum::ZOOM->value && !ZoomClient::make()->isConfigured($this->shop)) {
                $fail(__('Add the Zoom details in the shop settings first, so each appointment can get its own Zoom meeting.'));
            }
        };
    }

    private function googleCalendarIsConnected(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === AppointmentTypeMediaEnum::GOOGLE_MEET->value && !GoogleCalendarClient::isConnected($this->shop)) {
                $fail(__('Connect Google Calendar in the shop settings first, so each appointment can get its own Google Meet link.'));
            }
        };
    }

    private function belongsToMeetingMode(AppointmentTypeMeetingModeEnum $meetingMode, ?AppointmentType $appointmentType): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($meetingMode, $appointmentType) {
            if (!$appointmentType || $this->has('meeting_mode') || $appointmentType->meeting_mode === $meetingMode) {
                return;
            }

            $fail(__('Save the visit type as :mode first.', ['mode' => strtolower($meetingMode->label())]));
        };
    }

    /**
     * A store visit happens at a location, a video call on a media: each type keeps only its own.
     */
    protected function applyMeetingPlace(array $modelData, ?AppointmentType $appointmentType = null): array
    {
        $media       = Arr::pull($modelData, 'media');
        $meetingMode = Arr::get($modelData, 'meeting_mode', $appointmentType?->meeting_mode);
        $meetingMode = $meetingMode instanceof AppointmentTypeMeetingModeEnum ? $meetingMode : AppointmentTypeMeetingModeEnum::tryFrom((string) $meetingMode);
        $data        = $appointmentType?->data ?? [];

        if ($meetingMode === AppointmentTypeMeetingModeEnum::VIDEO_CALL) {
            $modelData['location'] = null;
            $data['media']         = $media ?: (Arr::get($data, 'media') ?: AppointmentTypeMediaEnum::WHATSAPP->value);
        } else {
            unset($data['media']);
        }

        $modelData['data'] = $data;

        return $modelData;
    }

    protected function applyStartTimes(array $modelData): array
    {
        if (!Arr::has($modelData, 'start_times')) {
            return $modelData;
        }

        $startTimes = collect((array) Arr::pull($modelData, 'start_times'))->map(fn ($time) => (string) $time)->unique()->sort()->values()->all();

        if ($startTimes) {
            $modelData['data']['start_times'] = $startTimes;
        } else {
            unset($modelData['data']['start_times']);
        }

        return $modelData;
    }

    protected function pullAvailability(array &$modelData): ?array
    {
        $availability = Arr::has($modelData, 'availability') ? (array) Arr::pull($modelData, 'availability') : null;

        if ($availability && Arr::has($availability, 'weekly')) {
            $weeklyHours = [];
            foreach (range(1, 7) as $dayOfWeek) {
                $weeklyHours[$dayOfWeek] = $this->sortedRanges((array) Arr::get($availability, "weekly.$dayOfWeek", []));
            }
            $modelData['weekly_hours'] = $weeklyHours;
        }

        return $availability;
    }

    protected function saveDates(AppointmentType $appointmentType, ?array $availability): void
    {
        if (!$availability || !Arr::has($availability, 'dates')) {
            return;
        }

        $today = now($appointmentType->shop->timezone?->name)->toDateString();
        $dates = collect((array) Arr::get($availability, 'dates'))
            ->filter(fn ($date) => Arr::get($date, 'date') >= $today)
            ->keyBy('date');

        $appointmentType->dates()
            ->where('date', '>=', $today)
            ->whereNotIn('date', $dates->keys()->all())
            ->delete();

        foreach ($dates as $date => $dateData) {
            $appointmentType->dates()->updateOrCreate(
                ['date' => $date],
                ['hours' => $this->sortedRanges((array) Arr::get($dateData, 'hours', []))]
            );
        }
    }

    protected function syncStaffOfMeetingMode(AppointmentType $appointmentType): void
    {
        $appointmentType->attendees()->sync(
            DB::table('appointment_type_user')
                ->join('appointment_types', 'appointment_types.id', '=', 'appointment_type_user.appointment_type_id')
                ->where('appointment_types.shop_id', $appointmentType->shop_id)
                ->where('appointment_types.meeting_mode', $appointmentType->meeting_mode->value)
                ->where('appointment_types.id', '!=', $appointmentType->id)
                ->whereNull('appointment_types.deleted_at')
                ->distinct()
                ->pluck('appointment_type_user.user_id')
                ->all()
        );
    }
}
