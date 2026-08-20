<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

trait WithSanitizedPhone
{
    private function sanitizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        $isInternational = str_starts_with($phone, '+');
        $digits          = preg_replace('/[^0-9]/', '', $phone);

        if ($digits === '') {
            return null;
        }

        return $isInternational ? '+'.$digits : $digits;
    }
}
