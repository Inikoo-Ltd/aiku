<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Notifications;

use App\Models\SysAdmin\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Work\WorkRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A mobile work notification (assigned, taken over, unassigned, cancelled, ready again, modified)
 * about a delivery note, picking session, customer return or pallet return. Title and body are
 * rendered once, in the recipient's language, so the Inbox row and the push say the same.
 */
class WorkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param array{type: string, id: int, reference: string, warehouse_id: int|null, state: string, type_group: string|null} $record
     * @param array{type: string, id: int|null, name: string|null} $actor
     * @param array<int, string> $changes
     */
    public function __construct(
        public string $kind,
        public string $event,
        public array $record,
        public array $actor,
        public array $changes,
        public string $title,
        public string $body,
    ) {
        $this->afterCommit();
    }

    /**
     * @param array{type: string, id: int, reference: string, warehouse_id: int|null, state: string, type_group: string|null} $record
     * @param array{type: string, id: int|null, name: string|null} $actor
     * @param array<int, string> $changes
     */
    public static function for(User $recipient, string $kind, string $event, array $record, array $actor, array $changes, ?string $holderName = null): self
    {
        [$title, $body] = self::texts($event, $record['reference'], $actor, $holderName, $recipient->language?->code ?: 'en');

        return new self($kind, $event, $record, $actor, $changes, $title, $body);
    }

    /**
     * @param array{type: string, id: int|null, name: string|null} $actor
     * @return array{0: string, 1: string}
     */
    public static function texts(string $event, string $reference, array $actor, ?string $holderName, string $locale): array
    {
        $actorLabel = match ($actor['type']) {
            'customer' => __('The customer', [], $locale),
            'system'   => 'Aiku',
            default    => (string) $actor['name'],
        };
        $replace = ['reference' => $reference, 'actor' => $event === 'taken_over' && $holderName ? $holderName : $actorLabel];

        return match ($event) {
            'assigned'    => [__(':reference assigned to you', $replace, $locale), __(':actor assigned :reference to you.', $replace, $locale)],
            'taken_over'  => [__(':reference taken over', $replace, $locale), __(':actor has taken over :reference, which was assigned to you.', $replace, $locale)],
            'unassigned'  => [__('Removed from :reference', $replace, $locale), __(':actor removed you from :reference.', $replace, $locale)],
            'cancelled'   => [__(':reference cancelled', $replace, $locale), __(':actor cancelled :reference, which is assigned to you.', $replace, $locale)],
            'ready_again' => [__(':reference can go ahead', $replace, $locale), __(':reference is no longer waiting and can be done again.', $replace, $locale)],
            'modified'    => [
                __(':reference was changed', $replace, $locale),
                $actor['type'] === 'user'
                    ? __('Heads up: :reference, assigned to you, was changed by :actor.', $replace, $locale)
                    : __(':actor has updated :reference, which is assigned to you.', $replace, $locale),
            ],
        };
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && WorkRecord::canBeNotifiedTo($notifiable, $this->kind, (int) $this->record['id']);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'work',
            'kind'    => $this->kind,
            'event'   => $this->event,
            'record'  => $this->record,
            'actor'   => $this->actor,
            'changes' => $this->changes,
            'title'   => $this->title,
            'body'    => $this->body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => ['title' => $this->title, 'body' => $this->body],
            'data'         => array_map(fn (mixed $value) => (string) $value, [
                'notification_id' => $this->id,
                'kind'            => $this->kind,
                'event'           => $this->event,
                'record_type'     => $this->record['type'],
                'record_id'       => $this->record['id'],
                'reference'       => $this->record['reference'],
                'warehouse_id'    => $this->record['warehouse_id'],
                'state'           => $this->record['state'],
                'type_group'      => $this->record['type_group'],
            ]),
            'android'      => [
                'priority'     => 'high',
                'notification' => ['tag' => $this->kind.':'.$this->record['id'], 'channel_id' => 'work'],
            ],
        ];
    }
}
