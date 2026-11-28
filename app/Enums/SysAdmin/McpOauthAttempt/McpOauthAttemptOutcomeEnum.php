<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\McpOauthAttempt;

use App\Enums\EnumHelperTrait;

enum McpOauthAttemptOutcomeEnum: string
{
    use EnumHelperTrait;

    case LOGIN_REQUIRED = 'login_required';
    case TWO_FA_REQUIRED = 'two_fa_required';
    case CONSENT_SHOWN = 'consent_shown';
    case NO_AI_ACCESS = 'no_ai_access';
    case CONNECTED = 'connected';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            'login_required'  => __('Asked to log in'),
            'two_fa_required' => __('Asked for 2FA code'),
            'consent_shown'   => __('Saw the connect page'),
            'no_ai_access'    => __('No AI assistant access'),
            'connected'       => __('Connected'),
            'cancelled'       => __('Cancelled'),
            'failed'          => __('Failed'),
        ];
    }
}
