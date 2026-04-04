<?php

namespace App\Enums\Notification;

enum NotificationChannelEnum: string
{
    case DATABASE = 'database';
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case PUSH = 'push';

    public function label(): string
    {
        return match ($this) {
            self::DATABASE => 'Database',
            self::EMAIL => 'Email',
            self::WHATSAPP => 'WhatsApp',
            self::PUSH => 'Push Notification',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
