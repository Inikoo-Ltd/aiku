<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 14:38:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum OrderImportRetryStatusEnum: string
{
    use EnumHelperTrait;

    case CHANNEL_UNAVAILABLE = 'channel_unavailable';
    case NOT_FOUND_ON_PLATFORM = 'not_found_on_platform';
    case ALREADY_IMPORTED = 'already_imported';
    case READY_TO_IMPORT = 'ready_to_import';
    case IMPORTED = 'imported';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::CHANNEL_UNAVAILABLE => __('Channel unavailable'),
            self::NOT_FOUND_ON_PLATFORM => __('Order not found on the platform'),
            self::ALREADY_IMPORTED => __('Order already imported'),
            self::READY_TO_IMPORT => __('Order found, ready to import'),
            self::IMPORTED => __('Import successful'),
            self::FAILED => __('Import failed'),
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::ALREADY_IMPORTED, self::READY_TO_IMPORT, self::IMPORTED]);
    }
}
