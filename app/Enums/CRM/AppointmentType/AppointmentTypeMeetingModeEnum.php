<?php

namespace App\Enums\CRM\AppointmentType;

use App\Enums\EnumHelperTrait;

enum AppointmentTypeMeetingModeEnum: string
{
    use EnumHelperTrait;

    case STORE_VISIT = 'store_visit';
    case VIDEO_CALL  = 'video_call';

    public function label(): string
    {
        return match ($this) {
            self::STORE_VISIT => __('Store visit'),
            self::VIDEO_CALL  => __('Video call'),
        };
    }

    public function icon(): array
    {
        return [
            'tooltip' => $this->label(),
            'icon'    => match ($this) {
                self::STORE_VISIT => 'fal fa-store-alt',
                self::VIDEO_CALL  => 'fal fa-video',
            },
        ];
    }
}
