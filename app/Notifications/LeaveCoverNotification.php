<?php

namespace App\Notifications;

use App\Events\BroadcastPersonalNotification;
use App\Models\HumanResources\Leave;
use Illuminate\Notifications\Notification;

class LeaveCoverNotification extends Notification
{
    public function __construct(public Leave $leave)
    {
    }

    public function via($notifiable): array
    {
        BroadcastPersonalNotification::dispatch($notifiable->id, ['id' => null, 'title' => $this->title(), 'body' => $this->body(), 'route' => route('grp.dashboard.show')]);

        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'leave_cover',
            'route' => route('grp.dashboard.show'),
        ];
    }

    private function title(): string
    {
        return __('You are covering for :name', ['name' => $this->leave->employee_name]);
    }

    private function body(): string
    {
        $period = __(':from to :to', [
            'from' => $this->leave->start_date->format('j M Y'),
            'to'   => $this->leave->end_date->format('j M Y'),
        ]);

        if (!$this->leave->cover_has_permissions) {
            return $period;
        }

        return $period.'. '.__('You have their permissions during this period.');
    }
}
