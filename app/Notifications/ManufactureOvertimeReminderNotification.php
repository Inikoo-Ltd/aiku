<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Production\Production;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ManufactureOvertimeReminderNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array<int, string>  $days  one line per artisan day, e.g. "Jane Doe, Sat 7 Nov: +5:00"
     */
    public function __construct(public Production $production, public Carbon $weekStart, public array $days)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __('Overtime to approve or reject before payroll locks tonight: :count days', ['count' => count($this->days)]);
    }

    public function body(): string
    {
        return implode("\n", $this->days);
    }

    public function url(): string
    {
        return route($this->route()['name'], $this->route()['parameters']);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'manufacture_overtime_reminder',
            'slug'  => $this->production->slug.'-'.$this->weekStart->toDateString(),
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()->subject($this->title());
        foreach ($this->days as $day) {
            $message->line($day);
        }

        return $message
            ->action(__('Open Daily hours'), $this->url())
            ->line(__('You get this email because you approve overtime in the factory.'));
    }

    private function route(): array
    {
        return [
            'name'       => 'grp.org.productions.show.artisans.daily_hours',
            'parameters' => [
                'organisation' => $this->production->organisation->slug,
                'production'   => $this->production->slug,
                'from'         => $this->weekStart->toDateString(),
                'to'           => $this->weekStart->copy()->addDays(6)->toDateString(),
                'open_only'    => 1,
            ],
        ];
    }
}
