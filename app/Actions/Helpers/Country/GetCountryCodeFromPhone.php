<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Country;

use App\Models\Helpers\Country;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCountryCodeFromPhone
{
    use AsObject;

    private const array PREFERRED_ON_SHARED_DIAL_CODE = ['US', 'GB', 'RU', 'AU', 'NO', 'MA', 'RE', 'GP', 'CW', 'FI'];

    /** @var array<string, string>|null */
    private static ?array $countryCodesByDialCode = null;

    /**
     * ponytail: longest dial-code prefix from the countries table, so +1 reads as the United
     * States for Canada too and a number is never validated; libphonenumber when that matters.
     */
    public function handle(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '' || !str_starts_with(trim((string) $phone), '+')) {
            return null;
        }

        $byDialCode = self::$countryCodesByDialCode ??= $this->countryCodesByDialCode();

        for ($length = min(7, strlen($digits)); $length >= 1; $length--) {
            $countryCode = $byDialCode[substr($digits, 0, $length)] ?? null;

            if ($countryCode) {
                return $countryCode;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function countryCodesByDialCode(): array
    {
        $byDialCode = [];

        foreach (Country::whereNotNull('phone_code')->orderBy('code')->get(['code', 'phone_code']) as $country) {
            foreach (explode('and', $country->phone_code) as $dialCode) {
                $dialCode = preg_replace('/\D/', '', $dialCode);

                if ($dialCode === '') {
                    continue;
                }

                if (!isset($byDialCode[$dialCode]) || in_array($country->code, self::PREFERRED_ON_SHARED_DIAL_CODE, true)) {
                    $byDialCode[$dialCode] = $country->code;
                }
            }
        }

        return $byDialCode;
    }
}
