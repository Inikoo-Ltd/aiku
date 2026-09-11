<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RefreshWebpagePageSpeed extends OrgAction
{
    use AsAction;

    public function handle(Webpage $webpage): void
    {
        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            QueueWebpagePageSpeed::run($webpage, $strategy);
        }
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisation($webpage->organisation, $request);

        $this->handle($webpage);
    }
}
