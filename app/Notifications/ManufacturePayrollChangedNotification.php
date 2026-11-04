<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 10:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Production\ManufacturePayrollAdjustment;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ManufacturePayrollChangedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public ManufacturePayrollAdjustment $adjustment)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __('Payroll changed after the lock: :worker, week of :week', [
            'worker' => $this->adjustment->user?->contact_name ?: $this->adjustment->user?->username,
            'week'   => $this->adjustment->pay_week->format('j M Y'),
        ]);
    }

    public function body(): string
    {
        return __(':user changed it (:amount). Reason: :reason. Please check and approve it.', [
            'user'   => $this->adjustment->changedBy?->contact_name ?: $this->adjustment->changedBy?->username,
            'amount' => ($this->adjustment->amount_delta >= 0 ? '+' : '').number_format((float) $this->adjustment->amount_delta, 2),
            'reason' => $this->adjustment->reason,
        ]);
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
            'type'  => 'manufacture_payroll_changed',
            'slug'  => (string) $this->adjustment->id,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title())
            ->line($this->body())
            ->action($this->adjustment->production->paysBySpeed() ? __('Open payroll') : __('Open payroll changes'), $this->url())
            ->line(__('You get this email because you approve changes to payroll after the weekly lock.'));
    }

    private function route(): array
    {
        $production = $this->adjustment->production;

        return [
            'name'       => $production->paysBySpeed() ? 'grp.org.productions.show.artisans.payroll' : 'grp.org.productions.show.artisans.changes_after_lock',
            'parameters' => [
                'organisation' => $production->organisation->slug,
                'production'   => $production->slug,
                'from'         => $this->adjustment->pay_week->toDateString(),
                'to'           => $this->adjustment->pay_week->copy()->addDays(6)->toDateString(),
            ],
        ];
    }
}
