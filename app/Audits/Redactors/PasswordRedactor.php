<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jul 2024 00:47:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Audits\Redactors;

class PasswordRedactor implements \OwenIt\Auditing\Contracts\AttributeRedactor
{
    public const SECRET_KEY_PATTERN = '/password|secret|token|pin|access_id|expires_at|\w*key$/i';

    /**
     * {@inheritdoc}
     */
    public static function redact($value): string
    {
        return '*********';
    }
}
