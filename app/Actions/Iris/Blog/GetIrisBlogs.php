<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Iris\Blog;

use App\Http\Resources\Web\BlogsIrisResource;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetIrisBlogs
{
    use AsController;

    public function handle(Website $website): AnonymousResourceCollection
    {
        return BlogsIrisResource::collection(
            IndexIrisBlogs::make()->handle($website)
        );
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        /** @var Website $website */
        $website = $request->input('website');

        return $this->handle($website);
    }
}
