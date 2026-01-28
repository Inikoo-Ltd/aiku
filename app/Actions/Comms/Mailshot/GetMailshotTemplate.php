<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 20 Jan 2026 09:57:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use Illuminate\Http\JsonResponse;

class GetMailshotTemplate extends OrgAction
{
    public function handle(Mailshot $mailshot): Mailshot
    {
        return $mailshot;
    }

    public function asController(Mailshot $mailshot): Mailshot
    {
        return $this->handle($mailshot);
    }

    public function jsonResponse(Mailshot $mailshot): JsonResponse
    {
        return response()->json($mailshot->email?->liveSnapshot?->layout);
    }
}
