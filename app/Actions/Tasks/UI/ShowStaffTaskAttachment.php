<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

use App\Actions\Helpers\Ticket\UI\ShowTicketAttachment;
use App\Models\Helpers\Media;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowStaffTaskAttachment
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isVisibleTo($request->user());
    }

    public function asController(StaffTask $staffTask, Media $media, ActionRequest $request): Response
    {
        abort_unless($staffTask->hasAttachment($media), 404);

        return ShowTicketAttachment::make()->handle($media, $request->boolean('contents'));
    }
}
