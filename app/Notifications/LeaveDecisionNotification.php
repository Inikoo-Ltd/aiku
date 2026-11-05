<?php

namespace App\Notifications;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class LeaveDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Leave $leave)
    {
    }

    public static function sendToEmployee(Leave $leave): void
    {
        $employee = $leave->employee;
        $email    = $employee?->email ?: $employee?->work_email;

        if ($email) {
            NotificationFacade::route('mail', $email)->notify(new self($leave));
        }
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $leave      = $this->leave->loadMissing(['leaveType', 'approver', 'organisation']);
        $isApproved = $leave->status === LeaveStatusEnum::APPROVED;

        $type    = ['type' => $leave->leaveType?->name ?? ucfirst($leave->type)];
        $subject = $isApproved
            ? __("Your ':type' request has been approved", $type)
            : __("Your ':type' request has been rejected", $type);

        $message = (new MailMessage())
            ->subject($subject)
            ->markdown('mail.leave-decision', [
                'shop'       => $leave->organisation->name,
                'shop_url'   => config('app.url'),
                'leave'      => $leave,
                'isApproved' => $isApproved,
            ]);

        if (app()->isProduction()) {
            $message->mailer('ses')->from($leave->organisation->email ?: 'help@aiku.io', $leave->organisation->name);
        }

        return $message;
    }
}
