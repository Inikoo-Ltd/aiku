<?php

namespace App\Notifications;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Leave $leave, public EmployeeLeaveBalance $balance)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $leave = $this->leave->loadMissing(['employee', 'leaveType', 'approver', 'organisation']);

        $pendingLeaveDays = Leave::where('employee_id', $leave->employee_id)
            ->where('status', LeaveStatusEnum::PENDING)
            ->sum('duration_days');

        return (new MailMessage())
            ->subject(__("APPROVED: ':type' for :name", [
                'type' => $leave->leaveType?->name ?? ucfirst($leave->type),
                'name' => $leave->employee_name,
            ]))
            ->markdown('mail.leave-approved', [
                'shop'             => $leave->organisation->name,
                'shop_url'         => config('app.url'),
                'leave'            => $leave,
                'balance'          => $this->balance,
                'pendingLeaveDays' => (float) $pendingLeaveDays,
            ]);
    }
}
