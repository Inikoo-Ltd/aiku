<?php

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
            abort(404, 'robots.txt not found for this website');
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
