<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 20:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\SupplierSubmission;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierPlanningNoteNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public SupplierSubmission $submission, public string $title, public string $body, public string $whyLine)
    {
        $this->locale = app()->getLocale();
        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'supplier_planning_note',
            'slug'  => (string) $this->submission->id,
            'route' => self::route($this->submission),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->plainLine($this->body))
            ->action(__('Open the planning note'), self::url($this->submission))
            ->line($this->plainLine($this->whyLine));
    }

    /**
     * @return array{name: string, parameters: array<int, int>}
     */
    public static function route(SupplierSubmission $submission): array
    {
        return ['name' => 'grp.supply-chain.supplier_submissions.show', 'parameters' => [$submission->id]];
    }

    public static function url(SupplierSubmission $submission): string
    {
        $route = self::route($submission);

        return route($route['name'], $route['parameters']);
    }
}
