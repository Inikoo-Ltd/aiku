<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\Comms\Email\SendAppointmentEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait WithAppointmentNotification
{
    protected function notifyVisitorAboutNewAppointment(Appointment $appointment): void
    {
        $code = match ($appointment->state) {
            AppointmentStateEnum::REQUESTED => OutboxCodeEnum::APPOINTMENT_REQUESTED,
            AppointmentStateEnum::ACCEPTED  => OutboxCodeEnum::APPOINTMENT_ACCEPTED,
            default                         => null,
        };

        $this->queueAppointmentEmail($appointment, $code);
    }

    protected function notifyVisitorAboutChange(Appointment $appointment, AppointmentStateEnum $previousState, Carbon $previousStartsAt): void
    {
        $state = $appointment->state;
        $moved = !$appointment->starts_at->equalTo($previousStartsAt);

        $code = match (true) {
            $state === AppointmentStateEnum::DECLINED && $previousState !== AppointmentStateEnum::DECLINED       => OutboxCodeEnum::APPOINTMENT_DECLINED,
            $state === AppointmentStateEnum::CANCELLED && $previousState->holdsSlot()                             => OutboxCodeEnum::APPOINTMENT_CANCELLED,
            $moved && $state->holdsSlot()                                                                          => OutboxCodeEnum::APPOINTMENT_RESCHEDULED,
            $state === AppointmentStateEnum::ACCEPTED && $previousState === AppointmentStateEnum::REQUESTED       => OutboxCodeEnum::APPOINTMENT_ACCEPTED,
            default                                                                                                => null,
        };

        $this->queueAppointmentEmail($appointment, $code, $code === OutboxCodeEnum::APPOINTMENT_RESCHEDULED ? $previousStartsAt->toIso8601String() : null);
    }

    private function queueAppointmentEmail(Appointment $appointment, ?OutboxCodeEnum $code, ?string $previousStartsAt = null): void
    {
        if (!$code || !$appointment->email) {
            return;
        }

        DB::afterCommit(fn () => SendAppointmentEmail::dispatch($appointment->id, $code->value, $previousStartsAt));
    }
}
