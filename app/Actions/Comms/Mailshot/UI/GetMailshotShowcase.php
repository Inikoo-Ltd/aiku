<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Http\Resources\Mail\MailshotResource;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMailshotShowcase
{
    use AsAction;

    public function handle(Mailshot $mailshot): array
    {
        return [
            'mailshot' => new MailshotResource($mailshot),
            'compiled_layout' => $mailshot->email?->liveSnapshot?->compiled_layout,
        ];
    }
}
