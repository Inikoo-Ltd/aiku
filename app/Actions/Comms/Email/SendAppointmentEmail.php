<?php

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\Appointment;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SendAppointmentEmail extends OrgAction
{
    use WithSendBulkEmails;

    public string $jobQueue = 'low-priority';

    private const PARAGRAPH = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

    public function handle(int $appointmentId, string $outboxCode, ?string $previousStartsAt = null): ?DispatchedEmail
    {
        $appointment = Appointment::with(['shop', 'appointmentType', 'visitor', 'user'])->find($appointmentId);
        $code        = OutboxCodeEnum::tryFrom($outboxCode);
        $visitor     = $appointment?->visitor;

        if (!$appointment || !$code || !($visitor instanceof Customer || $visitor instanceof Prospect)) {
            return null;
        }

        $emailAddress = $appointment->email ?: $visitor->email;
        if (!$emailAddress) {
            return null;
        }

        /** @var Outbox|null $outbox */
        $outbox = $appointment->shop->outboxes()->where('code', $code->value)->first();
        if (!$outbox || $outbox->state != OutboxStateEnum::ACTIVE || !$outbox->is_applicable || !$outbox->emailOngoingRun?->email?->liveSnapshot) {
            return null;
        }

        $emailHtmlBody = $outbox->builder == OutboxBuilderEnum::BLADE
            ? Arr::get($outbox->emailOngoingRun->email->liveSnapshot->layout, 'blade_template')
            : $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        if (!$emailHtmlBody) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($appointment->shop->language->code);

        try {
            return $this->send($appointment, $code, $outbox, $visitor, $emailAddress, $emailHtmlBody, $previousStartsAt ? Carbon::parse($previousStartsAt) : null);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function send(Appointment $appointment, OutboxCodeEnum $code, Outbox $outbox, Customer|Prospect $visitor, string $emailAddress, string $emailHtmlBody, ?Carbon $previous): DispatchedEmail
    {
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $visitor, [
            'outbox_id'     => $outbox->id,
            'email_address' => $emailAddress,
        ]);
        $dispatchedEmail->refresh();

        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $this->subject($appointment, $code, $outbox),
            $emailHtmlBody,
            '',
            additionalData: [
                'customer_name' => $appointment->contact_name,
                'shop_name'     => $appointment->shop->name,
                'email_body'    => $this->generateBodyHtml($appointment, $code, $previous),
            ],
            senderName: $outbox->emailOngoingRun->senderName(),
            attachments: [
                [
                    'content'  => $this->calendarInvite($appointment, $code),
                    'filename' => 'appointment.ics',
                ],
            ]
        );
    }

    public function subject(Appointment $appointment, OutboxCodeEnum $code, Outbox $outbox): string
    {
        $date    = $this->localStart($appointment)->translatedFormat('j F Y');
        $subject = $outbox->emailOngoingRun?->email?->subject;

        if ($subject && $subject !== $code->label()) {
            return str_replace(['[Shop Name]', '[Appointment Date]'], [$appointment->shop->name, $date], $subject);
        }

        return match ($code) {
            OutboxCodeEnum::APPOINTMENT_REQUESTED   => __('We have received your appointment request for :date', ['date' => $date]),
            OutboxCodeEnum::APPOINTMENT_ACCEPTED    => __('Your appointment on :date is confirmed', ['date' => $date]),
            OutboxCodeEnum::APPOINTMENT_DECLINED    => __('We cannot see you on :date', ['date' => $date]),
            OutboxCodeEnum::APPOINTMENT_RESCHEDULED => __('Your appointment has moved to :date', ['date' => $date]),
            default                                 => __('Your appointment on :date is cancelled', ['date' => $date]),
        };
    }

    public function generateBodyHtml(Appointment $appointment, OutboxCodeEnum $code, ?Carbon $previousStartsAt = null): string
    {
        $start = $this->localStart($appointment);
        $when  = ['date' => $start->translatedFormat('l j F Y'), 'time' => $start->format('H:i')];

        $html = $this->paragraph(__('Hello :name,', ['name' => e($appointment->contact_name)]));

        $html .= match ($code) {
            OutboxCodeEnum::APPOINTMENT_REQUESTED => $this->paragraph(__('Thank you for booking. We have received your request for :date at :time and will confirm it shortly.', $when)),
            OutboxCodeEnum::APPOINTMENT_ACCEPTED  => $this->paragraph(__('Your appointment on :date at :time is confirmed. We look forward to seeing you.', $when)),
            OutboxCodeEnum::APPOINTMENT_DECLINED  => $this->paragraph(__('We are sorry, we cannot see you on :date at :time.', $when))
                .($appointment->state_reason ? $this->paragraph(e($appointment->state_reason)) : '')
                .$this->paragraph(__('You are welcome to book another time that suits you.')),
            OutboxCodeEnum::APPOINTMENT_RESCHEDULED => $this->paragraph(
                $previousStartsAt
                    ? __('Your appointment has moved from :previous to :date at :time.', [
                        ...$when,
                        'previous' => $previousStartsAt->copy()->setTimezone($start->timezone)->translatedFormat('l j F Y, H:i'),
                    ])
                    : __('Your appointment has moved to :date at :time.', $when)
            ),
            default => $this->paragraph(__('Your appointment on :date at :time has been cancelled.', $when)),
        };

        if (in_array($code, [OutboxCodeEnum::APPOINTMENT_REQUESTED, OutboxCodeEnum::APPOINTMENT_ACCEPTED, OutboxCodeEnum::APPOINTMENT_RESCHEDULED], true)) {
            $html .= $this->detailsTable($appointment);
        }

        $html .= $this->paragraph(__('If you have any questions, reply to this email.'));

        return $html;
    }

    public function calendarInvite(Appointment $appointment, OutboxCodeEnum $code): string
    {
        $isCancelled = in_array($code, [OutboxCodeEnum::APPOINTMENT_DECLINED, OutboxCodeEnum::APPOINTMENT_CANCELLED], true);
        $format      = fn (Carbon $time) => $time->copy()->utc()->format('Ymd\THis\Z');
        $escape      = fn (?string $text) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], (string) $text);
        $domain      = $appointment->shop->website?->domain ?? 'aiku.io';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Aiku//Appointments//EN',
            'METHOD:'.($isCancelled ? 'CANCEL' : 'REQUEST'),
            'BEGIN:VEVENT',
            'UID:appointment-'.$appointment->id.'@'.$domain,
            'SEQUENCE:'.$appointment->updated_at->timestamp,
            'DTSTAMP:'.$format(now()),
            'DTSTART:'.$format($appointment->starts_at),
            'DTEND:'.$format($appointment->ends_at),
            'SUMMARY:'.$escape(($appointment->appointmentType?->name ?? __('Appointment')).' · '.$appointment->shop->name),
            'STATUS:'.($isCancelled ? 'CANCELLED' : ($code === OutboxCodeEnum::APPOINTMENT_REQUESTED ? 'TENTATIVE' : 'CONFIRMED')),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        if ($location = $appointment->appointmentType?->location) {
            array_splice($lines, -3, 0, ['LOCATION:'.$escape($location)]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function detailsTable(Appointment $appointment): string
    {
        $start = $this->localStart($appointment);
        $type  = $appointment->appointmentType;
        $rows  = [
            __('What')  => $type?->name.($type ? ' · '.$type->meeting_mode->label() : ''),
            __('When')  => $start->translatedFormat('l j F Y').', '.$start->format('H:i').'–'.$appointment->ends_at->copy()->setTimezone($start->timezone)->format('H:i').' ('.$start->timezone->getName().')',
            __('Where') => $type?->meeting_mode === AppointmentTypeMeetingModeEnum::VIDEO_CALL
                ? __('Video call on WhatsApp, we will call you on :phone', ['phone' => $appointment->phone ?: '-'])
                : $type?->location,
        ];
        if ($appointment->user) {
            $rows[__('With')] = $appointment->user->contact_name;
        }

        $cell = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; padding: 6px 12px 6px 0; vertical-align: top;"';
        $html = '<table cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border-top: 1px solid #e9e9e9; width: 100%;">';
        foreach (array_filter($rows) as $label => $value) {
            $html .= '<tr><td '.$cell.'><strong>'.e($label).'</strong></td><td '.$cell.'>'.e($value).'</td></tr>';
        }

        return $html.'</table>';
    }

    private function paragraph(string $text): string
    {
        return '<p '.self::PARAGRAPH.'>'.$text.'</p>';
    }

    private function localStart(Appointment $appointment): Carbon
    {
        return $appointment->starts_at->copy()->setTimezone($appointment->shop->timezone?->name ?? 'UTC');
    }
}
