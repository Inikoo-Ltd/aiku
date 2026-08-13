<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Blog\IndexIrisBlogs;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\BlogsIrisResource;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisBusinessTipsDashboard
{
    use AsAction;

    private const SUB_TYPES = [WebpageSubTypeEnum::BUSINESS_TIPS];

    public function handle(Website $website): LengthAwarePaginator
    {
        return IndexIrisBlogs::make()->handle($website, IndexIrisBlogs::PREFIX, self::SUB_TYPES);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        /** @var Website $website */
        $website = $request->input('website');

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $blogs, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->input('website');

        return Inertia::render(
            'BlogDashboard',
            [
                'title' => __('Business Tips'),
                'data'  => BlogsIrisResource::collection($blogs),
            ]
        )->table(IndexIrisBlogs::make()->tableStructure($website, IndexIrisBlogs::PREFIX, self::SUB_TYPES));
    }
}
