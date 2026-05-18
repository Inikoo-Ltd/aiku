<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Http\Resources\Web\CrawlsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCrawls extends OrgAction
{
    use WithWebAuthorisation;

    private Website $parent;

    protected function getElementGroups(Website $website): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    CrawlStateEnum::labels(),
                    CrawlStateEnum::count($website)
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('crawls.state', $elements);
                }
            ],
        ];
    }

    public function handle(Website $website, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('crawls.trigger', $value)
                    ->orWhereStartWith('crawls.state', $value)
                    ->orWhereStartWith('crawls.type', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Crawl::class);
        $queryBuilder->where('crawls.website_id', $website->id);

        foreach ($this->getElementGroups($website) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-crawls.id')
            ->select([
                'crawls.id',
                'crawls.state',
                'crawls.trigger',
                'crawls.type',
                'crawls.running',
                'crawls.finish_reason',
                'crawls.start_at',
                'crawls.end_at',
                'crawls.urls_processed',
                'crawls.urls_found',
                'crawls.depth',
                'crawls.concurrency',
                'crawls.created_at',
                'crawls.updated_at',
            ])
            ->allowedSorts(['id', 'state', 'trigger', 'type', 'start_at', 'end_at', 'urls_processed', 'urls_found', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title'       => __('No crawls found'),
                        'description' => __('No crawl history for this website yet'),
                    ],
                )
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'id', label: __('ID'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'trigger', label: __('Trigger'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'start_at', label: __('Start'), canBeHidden: false, sortable: true, searchable: false, type: 'date')
                ->column(key: 'end_at', label: __('End'), canBeHidden: false, sortable: true, searchable: false, type: 'date')
                ->column(key: 'urls_processed', label: __('URLs Processed'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'urls_found', label: __('URLs Found'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'finish_reason', label: __('Finish Reason'), canBeHidden: false, sortable: false, searchable: false)
                ->defaultSort('-id');
        };
    }

    public function jsonResponse(LengthAwarePaginator $crawls): AnonymousResourceCollection
    {
        return CrawlsResource::collection($crawls);
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }
}
