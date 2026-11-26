<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 19:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject\UI;

use App\Actions\Helpers\Ticket\UI\ShowTicketAttachment;
use App\Models\Helpers\Media;
use App\Models\Helpers\TicketProject;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowTicketProjectFile
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return ShowTicketProject::make()->authorize($request);
    }

    public function asController(TicketProject $ticketProject, Media $media, ActionRequest $request): Response
    {
        abort_unless($ticketProject->hasFile($media), 404);

        return ShowTicketAttachment::make()->handle($media, $request->boolean('contents'));
    }
}
