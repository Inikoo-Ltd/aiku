<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Rules;

use App\Actions\Iris\Docs\ShowIrisDocs;
use Illuminate\Contracts\Validation\ValidationRule;

class NotReservedIrisPath implements ValidationRule
{
    public const array RESERVED = [ShowIrisDocs::PATH];

    public function validate($attribute, $value, $fail): void
    {
        $firstSegment = strtolower(explode('/', trim((string) $value, '/'))[0]);

        if (in_array($firstSegment, self::RESERVED, true)) {
            $fail(__('":path" is used by the website itself, choose another address.', ['path' => $firstSegment]));
        }
    }
}
