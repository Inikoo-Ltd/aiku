<?php

/*
 * Author: Aiku Development Team
 * Created: Mon, 16 Dec 2024 09:04:00 Malaysia Time
 * Copyright (c) 2024, Inikoo Ltd
 */

namespace App\Actions\Web\Website\LlmsTxt;

use App\Models\Web\Website;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ServeLlmsTxt
{
    use AsAction;

    public function handle(Website $website): Response
    {
        $content = GetLlmsTxt::run($website);

        if ($content === null) {
            abort(404, 'LLMs.txt not found');
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->get('website');

        return $this->handle($website);
    }
}
