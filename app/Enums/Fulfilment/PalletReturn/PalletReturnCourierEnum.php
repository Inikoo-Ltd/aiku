<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 21 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;

enum PalletReturnCourierEnum: string
{
    use EnumHelperTrait;

    case DPD         = 'dpd';
    case ROYAL_MAIL  = 'royal_mail';
    case GLS         = 'gls';
    case SLOVAK_POST = 'slovak_post';

    public static function labels(): array
    {
        return [
            'dpd'         => 'DPD',
            'royal_mail'  => 'Royal Mail',
            'gls'         => 'GLS',
            'slovak_post' => 'Slovak Post',
        ];
    }

    /**
     * @return array<int, array{name: string, code: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['name' => $label, 'code' => $code])
            ->values()
            ->all();
    }
}
