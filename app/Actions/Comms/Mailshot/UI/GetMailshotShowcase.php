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
        $email          = $mailshot->email;
        $compiledLayout = $email?->liveSnapshot?->compiled_layout;
        $snapshot       = $email?->unpublishedSnapshot;
        $bytes = strlen($compiledLayout);
        $kb    = round(($bytes / 1024), 2);
        return [
            'mailshot' => new MailshotResource($mailshot),
            'compiled_layout' => $compiledLayout,
            'compiled_layout_size' => $kb,
            'is_composed' => (bool) $compiledLayout || ($snapshot && !$snapshot->updated_at->eq($snapshot->created_at)),
        ];
    }
}
