<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\RememberVerifiedChatFact;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The shop facts the assistant's reviewers checked and keep for the next runs, each with how it
 * was checked and the drafts staff turned down that cited it. Nobody approves them first, so
 * this is where staff see what the assistant now takes as true and remove what is wrong; a
 * removed fact cannot be stored again by the assistant, and restoring it only puts it back to be
 * checked again.
 */
class IndexChatVerifiedFacts extends OrgAction
{
    use WithChatAgentAuthorisation;

    /** @var Collection<int, int> */
    private Collection $shopIds;

    public function authorize(ActionRequest $request): bool
    {
        $this->shopIds = IndexChatAikuDrafts::make()->shopIdsFor($request->user());

        return $this->shopIds->isNotEmpty();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->shopIds);
    }

    /**
     * @param  Collection<int, int>  $shopIds
     */
    public function handle(Collection $shopIds, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->where('title', 'ilike', '%'.$value.'%')
                    ->orWhere('body', 'ilike', '%'.$value.'%');
            });
        });

        return QueryBuilder::for(ChatKnowledgeEntry::with('shop:id,name')->where('source_type', RememberVerifiedChatFact::SOURCE)->whereIn('shop_id', $shopIds))
            ->defaultSort('-hydrated_at')
            ->allowedSorts(['hydrated_at', 'title', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'hydrated_at', label: __('Checked'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_name', label: __('Shop'))
                ->column(key: 'status', label: __('Status'), sortable: true)
                ->column(key: 'title', label: __('Fact'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '')
                ->defaultSort('-hydrated_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $facts, ActionRequest $request): Response
    {
        $facts->getCollection()->transform(fn (ChatKnowledgeEntry $fact) => [
            'id'            => $fact->id,
            'hydrated_at'   => $fact->hydrated_at?->toIso8601String(),
            'shop_name'     => $fact->shop?->name,
            'status'        => $fact->status,
            'trusted'       => $fact->status === 'active' && $fact->expires_at?->isFuture(),
            'title'         => $fact->title,
            'body'          => $fact->body,
            'expires_at'    => $fact->expires_at?->toIso8601String(),
            'evidence'      => data_get(Arr::last(data_get($fact->evidence, 'verifications', [])), 'evidence'),
            'chats_checked' => count(array_unique(array_column(data_get($fact->evidence, 'verifications', []), 'chat'))),
            'disputes'      => array_values(array_column(data_get($fact->evidence, 'disputes', []), 'reason')),
            'conflict'      => $fact->conflict,
        ]);

        return Inertia::render('Chat/ChatVerifiedFacts', [
            'breadcrumbs' => ShowGroupChatAutomation::make()->getBreadcrumbs(),
            'title'       => __('Verified facts'),
            'pageHead'    => [
                'title'         => __('Verified facts'),
                'icon'          => ['title' => __('AI assist'), 'icon' => ['fal', 'fa-robot']],
                'subNavigation' => ShowGroupChatAutomation::make()->subNavigation($this->group),
            ],
            'data' => JsonResource::collection($facts),
        ])->table($this->tableStructure());
    }
}
