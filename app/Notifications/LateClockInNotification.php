<?php

namespace App\Notifications;

use App\Models\HumanResources\Clocking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateClockInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Clocking $clocking)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $clockedAt = $this->clocking->clocked_at->format('H:i');
        $scheduleName = $this->clocking->workSchedule?->name ?? 'Default Schedule';

        return (new MailMessage())
            ->subject(__('Late Clock In Notification'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?? $notifiable->alias]))
            ->line(__('You have clocked in late today.'))
            ->line(__('Clock In Time: :time', ['time' => $clockedAt]))
            ->line(__('Schedule: :schedule', ['schedule' => $scheduleName]))
            ->line(__('Please ensure to clock in on time going forward.'));
    }

    public function toArray($notifiable): array
    {
        return [
            'clocking_id' => $this->clocking->id,
            'clocked_at' => $this->clocking->clocked_at->toDateTimeString(),
            'is_late' => true,
            'schedule_id' => $this->clocking->work_schedule_id,
        ];
    }
}
