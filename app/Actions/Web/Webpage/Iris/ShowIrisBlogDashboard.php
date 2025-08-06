<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\BlogWebpagesResource;
use App\Http\Resources\Web\WebpagesResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisBlogDashboard
{
    use AsAction;

    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }


    public function handle(ActionRequest $request): Response
    {
        $website = $request->get('website');

        $blogs = BlogWebpagesResource::collection($website->webpages()->where('type', WebpageTypeEnum::BLOG)->get())->resolve();

        return Inertia::render(
            'BlogDashboard', 
            [
                'blogs' => $blogs,
            ]
        );
    }
}
