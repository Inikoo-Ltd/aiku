<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\User;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

enum UserNotificationEnum: string
{
    case TICKET_RESOLVED = 'ticket_resolved';
    case TICKET_COMMENT = 'ticket_comment';
    case TICKET_MENTION = 'ticket_mention';
    case TICKET_NEEDS_REPLY = 'ticket_needs_reply';

    public const array CHANNELS = ['email', 'slack'];

    public function label(): string
    {
        return match ($this) {
            self::TICKET_RESOLVED    => __('My ticket is resolved'),
            self::TICKET_COMMENT     => __('New comment on my ticket'),
            self::TICKET_MENTION     => __('Someone @mentions me on a ticket'),
            self::TICKET_NEEDS_REPLY => __('My ticket needs more information from me'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function channelsFor(User $user): array
    {
        $chosen = Arr::get($user->settings, 'notifications.'.$this->value);
        if (is_array($chosen)) {
            return array_values(array_intersect($chosen, self::CHANNELS));
        }

        if ($this === self::TICKET_COMMENT) {
            return [];
        }

        return match (Arr::get($user->settings, 'ticket_notifications', 'both')) {
            'email' => ['email'],
            'slack' => ['slack'],
            'none'  => [],
            default => self::CHANNELS,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TICKET_RESOLVED    => 'fal fa-check-double',
            self::TICKET_COMMENT     => 'fal fa-comment-lines',
            self::TICKET_MENTION     => 'fal fa-at',
            self::TICKET_NEEDS_REPLY => 'fal fa-question-circle',
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function valuesFor(User $user): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $event) => [$event->value => $event->channelsFor($user)])->all();
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $event) => ['value' => $event->value, 'label' => $event->label(), 'icon' => $event->icon()])->all();
    }
}
