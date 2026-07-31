<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Http\Resources\Web\BlogsIrisResource;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Iris\Blog\IndexIrisBlogs;

class ShowIrisBlogDashboard
{
    use AsAction;

    public function handle(Website $website): LengthAwarePaginator
    {
        return IndexIrisBlogs::make()->handle($website, IndexIrisBlogs::PREFIX);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        /** @var Website $website */
        $website = $request->input('website');

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $blogs): Response
    {
        return Inertia::render(
            'BlogDashboard',
            [
                'data' => BlogsIrisResource::collection($blogs),
            ]
        )->table(IndexIrisBlogs::make()->tableStructure(IndexIrisBlogs::PREFIX));
    }
}
