<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use RuntimeException;

class SlackWriteException extends RuntimeException
{
    public function __construct(public readonly string $slackError, public readonly string $slackMethod)
    {
        parent::__construct("Slack $slackMethod failed: $slackError");
    }
}
