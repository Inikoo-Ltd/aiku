<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\DispatchedEmail;

use App\Enums\EnumHelperTrait;

enum DispatchedEmailProviderEnum: string
{
    use EnumHelperTrait;

    case SES = 'ses';
    case MAILGUN = 'mailgun';
    case RESEND = 'resend';
    case MAILERSEND = 'mailersend';
    case ALIBABA = 'alibaba';
    case SENDMAIL = 'sendmail';

}
