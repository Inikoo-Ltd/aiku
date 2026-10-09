<?php

namespace App\Actions\CRM\AppointmentType;

use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\CRM\AppointmentType;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
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
            'location'                      => [...$optional, 'nullable', 'string', 'max:255'],
            'duration_minutes'              => [...$required, 'integer', 'min:5', 'max:480'],
            'buffer_minutes'                => [...$optional, 'integer', 'min:0', 'max:240'],
            'min_notice_hours'              => [...$optional, 'integer', 'min:0', 'max:720'],
            'booking_window_days'           => [...$optional, 'integer', 'min:1', 'max:365'],
            'capacity_per_slot'             => [...$optional, 'integer', 'min:1', 'max:100'],
            'is_active'                     => [...$optional, 'boolean'],
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
}
