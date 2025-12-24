<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Dec 2025 17:29:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Models\Web\Website;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowIrisRobotsTxt
{
    use AsController;

    public function asController(ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->get('website');

        $filePath = "robots/robots_{$website->id}.txt";

        if (!Storage::disk('local')->exists($filePath)) {
            return response(
                'User-agent: *',
                200,
                [
                    'Content-Type'  => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=3600',
                ]
            );

        }

        return response(
            Storage::disk('local')->get($filePath),
            200,
            [
                'Content-Type'  => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
