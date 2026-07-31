<?php

namespace App\Actions\Iris\Blog;

use App\Actions\IrisAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisBlogs extends IrisAction
{
    public const PREFIX = 'blogs';

    public function handle(Website $website, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('webpages.title', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Webpage::class)
            ->where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->select([
                'webpages.id',
                'webpages.title',
                'webpages.url',
                'webpages.canonical_url',
                'webpages.published_layout',
                'webpages.live_at',
                'webpages.last_published_at',
            ])
            ->defaultSort('-webpages.live_at')
            ->allowedSorts(['title', 'last_published_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('blog'), __('blogs')])
                ->withEmptyState(
                    [
                        'title' => __('No blog posts available yet'),
                    ]
                )
                ->column(key: 'image_src', label: __('Image'), align: 'center')
                ->column(key: 'title', label: __('Title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'last_published_at', label: __('Published'), sortable: true)
                ->column(key: 'url', label: __('Webpage'), align: 'center');
        };
    }

    public function action(Website $website, ActionRequest $request, ?string $prefix = null): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisation($request);

        return $this->handle($website, $prefix);
    }
}
