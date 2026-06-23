<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 23 Jun 2026 00:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotPerformanceInsightMetricEnum: string
{
    use EnumHelperTrait;

    case TOTAL_EMAIL_OPENED = 'total_email_opened';
    case TOTAL_CLICK = 'total_click';
    case OPEN_RATE = 'open_rate';
    case CLICK_RATE = 'click_rate';
    case SPAM_RATE = 'spam_rate';
    case UNSUBSCRIBE_RATE = 'unsubscribe_rate';
    case BOUNCE_RATE = 'bounce_rate';
}
