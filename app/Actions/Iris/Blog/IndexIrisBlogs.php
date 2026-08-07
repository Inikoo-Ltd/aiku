<?php

namespace App\Actions\Iris\Blog;

use App\Actions\IrisAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexIrisBlogs extends IrisAction
{
    public const PREFIX = 'blogs';

    public const SUB_TYPES = [
        WebpageSubTypeEnum::BLOG,
        WebpageSubTypeEnum::DAVIDS_TRAVEL_BLOG,
        WebpageSubTypeEnum::TIPS,
    ];

    /**
     * @param  array<int, WebpageSubTypeEnum>  $subTypes
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: \Closure}>
     */
    protected function getElementGroups(Website $website, array $subTypes): array
    {
        if (count($subTypes) < 2) {
            return [];
        }

        $counts = Webpage::where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereIn('webpages.sub_type', $subTypes)
            ->groupBy('webpages.sub_type')
            ->selectRaw('webpages.sub_type, count(*) as total')
            ->pluck('total', 'webpages.sub_type');

        $labels = WebpageSubTypeEnum::labels();

        return [
            'sub_type' => [
                'label'    => __('Category'),
                'elements' => collect($subTypes)->mapWithKeys(fn (WebpageSubTypeEnum $subType) => [
                    $subType->value => [
                        Str::ucfirst(Arr::get($labels, $subType->value, $subType->value)),
                        (int) Arr::get($counts, $subType->value, 0),
                    ],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('webpages.sub_type', $elements);
                },
            ],
        ];
    }

    protected function getPublishedAtSortExpression(): string
    {
        $layoutPublishedDate = "NULLIF(webpages.published_layout #>> '{web_blocks,0,web_block,layout,data,fieldValue,published_date}', '')";

        return "COALESCE(
            CASE WHEN pg_input_is_valid($layoutPublishedDate, 'timestamptz') THEN ($layoutPublishedDate)::timestamptz END,
            (SELECT snapshots.published_at FROM snapshots WHERE snapshots.id = webpages.live_snapshot_id),
            webpages.last_published_at,
            webpages.live_at
        )";
    }

    /**
     * @param  array<int, WebpageSubTypeEnum>|null  $subTypes
     */
    public function handle(Website $website, ?string $prefix = null, ?array $subTypes = null): LengthAwarePaginator
    {
        $subTypes = $subTypes ?? self::SUB_TYPES;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('webpages.title', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });

        $publishedAtSort = AllowedSort::callback('last_published_at', function ($query, bool $descending) {
            $newestFirst = !$descending;

            $query->orderByRaw($this->getPublishedAtSortExpression().' '.($newestFirst ? 'desc' : 'asc').' nulls last');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Webpage::class);

        foreach ($this->getElementGroups($website, $subTypes) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->whereIn('webpages.sub_type', $subTypes)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->select([
                'webpages.id',
                'webpages.title',
                'webpages.url',
                'webpages.canonical_url',
                'webpages.published_layout',
                'webpages.live_at',
                'webpages.last_published_at',
                'webpages.live_snapshot_id',
            ])
            ->with('liveSnapshot:id,published_at')
            ->defaultSort('-webpages.live_at')
            ->allowedSorts(['title', $publishedAtSort])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('sub_type')])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @param  array<int, WebpageSubTypeEnum>|null  $subTypes
     */
    public function tableStructure(Website $website, ?string $prefix = null, ?array $subTypes = null): Closure
    {
        $subTypes = $subTypes ?? self::SUB_TYPES;

        return function (InertiaTable $table) use ($website, $prefix, $subTypes) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($website, $subTypes) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
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

    /**
     * @param  array<int, WebpageSubTypeEnum>|null  $subTypes
     */
    public function action(Website $website, ActionRequest $request, ?string $prefix = null, ?array $subTypes = null): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisation($request);

        return $this->handle($website, $prefix, $subTypes);
    }
}
