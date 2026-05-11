<?php

/*
 * Author: Eka yudinata <ekayudinata@gmail.com>
 * Created: Mon, 05 May 2026 16:19:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowUnsubscribeError extends GrpAction
{
    use AsController;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render('EmailFallbackOnError');
    }

    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }
}
