<?php

namespace App\Actions\CRM\Appointment\GoogleCalendar;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\CRM\Appointment;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use App\Services\GoogleCalendar\ReleaseWhenGoogleCalendarRateLimited;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The event follows the appointment: it is written when the appointment is booked, moved or
 * changed, and removed when it is declined or cancelled. A request still waiting for an answer is
 * a tentative event. A Google Meet video call gets its Meet on the event, and the appointment
 * keeps the link.
 */
class SyncAppointmentGoogleCalendarEvent
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public int $jobTries = 10;

    public int $jobMaxExceptions = 3;

    public function getJobMiddleware(): array
    {
        return [new ReleaseWhenGoogleCalendarRateLimited()];
    }

    public static function dispatchFor(Appointment $appointment): void
    {
        if (GoogleCalendarClient::isConnected($appointment->shop)) {
            static::dispatch($appointment->id);
        }
    }

    /**
     * @throws RequestException
     */
    public function handle(int $appointmentId): void
    {
        Cache::lock('appointment-google-calendar-'.$appointmentId, 30)->block(20, function () use ($appointmentId) {
            $appointment = Appointment::withTrashed()->with(['shop', 'appointmentType', 'user'])->find($appointmentId);
            $client      = $appointment ? GoogleCalendarClient::forShop($appointment->shop) : null;

            if (!$client) {
                return;
            }

            if (!$this->belongsInCalendar($appointment)) {
                $client->deleteEvent($appointment->id);
                $this->storeGoogleMeet($appointment, null);

                return;
            }

            $this->write($client, $appointment);
        });
    }

    /**
     * @throws RequestException
     */
    public function write(GoogleCalendarClient $client, Appointment $appointment): Appointment
    {
        $saved = $client->saveEvent($appointment->id, $this->event($appointment));

        if (!$appointment->appointmentType?->isGoogleMeetCall()) {
            return $this->storeGoogleMeet($appointment, null);
        }

        if (!$joinUrl = Arr::get($saved, 'hangoutLink')) {
            return $appointment;
        }

        return $this->storeGoogleMeet($appointment, [
            'join_url'        => $joinUrl,
            'conference_data' => Arr::only(Arr::get($saved, 'conferenceData', []), ['conferenceId', 'conferenceSolution', 'entryPoints']),
        ]);
    }

    private function storeGoogleMeet(Appointment $appointment, ?array $meet): Appointment
    {
        $data = $appointment->data ?? [];

        if (Arr::get($data, 'google_meet') == $meet) {
            return $appointment;
        }

        if ($meet) {
            $data['google_meet'] = $meet;
        } else {
            unset($data['google_meet']);
        }

        $appointment->updateQuietly(['data' => $data]);

        return $appointment;
    }

    public function belongsInCalendar(Appointment $appointment): bool
    {
        return !$appointment->trashed() && !in_array($appointment->state, AppointmentStateEnum::closed(), true);
    }

    public function event(Appointment $appointment): array
    {
        $timezone = $appointment->shop->timezone?->name ?? 'UTC';
        $type     = $appointment->appointmentType;

        $description = array_filter([
            $appointment->state->label(),
            $appointment->email ? __('Email').': '.$appointment->email : null,
            $appointment->phone ? __('Phone').': '.$appointment->phone : null,
            $type?->meeting_mode === AppointmentTypeMeetingModeEnum::STORE_VISIT ? __('People coming').': '.$appointment->number_visitors : null,
            $appointment->user ? __('With').': '.$appointment->user->contact_name : null,
            $appointment->notes,
            route('grp.org.shops.show.crm.appointments.show', [$appointment->organisation->slug, $appointment->shop->slug, $appointment->id]),
        ]);

        $event = [
            'summary'            => $appointment->contact_name.($type ? ' · '.$type->name : ''),
            'description'        => implode("\n", $description),
            'location'           => $type?->media() ? __('Video call on :media', ['media' => $type->media()->label()]) : $type?->location,
            'status'             => $appointment->state === AppointmentStateEnum::REQUESTED ? 'tentative' : 'confirmed',
            'start'              => ['dateTime' => $appointment->starts_at->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
            'end'                => ['dateTime' => $appointment->ends_at->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
            'extendedProperties' => ['private' => [GoogleCalendarClient::EVENT_PROPERTY => (string) $appointment->id]],
        ];

        if ($type?->isGoogleMeetCall()) {
            $event['conferenceData'] = data_get($appointment->data, 'google_meet.conference_data') ?: [
                'createRequest' => [
                    'requestId'             => 'aiku-appointment-'.$appointment->id,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $event;
    }
}
