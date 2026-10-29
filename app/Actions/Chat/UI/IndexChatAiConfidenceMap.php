<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Reports\GetChatAiConfidenceMap;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatAiRiskEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatAiDraft;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every kind of chat the assistant drafted for, one row per shop, intent and risk, with what
 * staff did with the drafts and the lower bound that decides whether that kind could ever be
 * answered without a person. Nothing is sent without a person yet: every row is at level draft.
 */
class IndexChatAiConfidenceMap extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'nullable', 'integer', 'in:'.implode(',', GetChatAiConfidenceMap::DAYS)],
        ];
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, (int) ($this->validatedData['days'] ?? 30));
    }

    public function handle(Group $group, int $days): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->where('cells.shop_name', 'ilike', '%'.$value.'%')
                    ->orWhere('cells.intent', 'ilike', '%'.$value.'%')
                    ->orWhere('cells.risk', 'ilike', '%'.$value.'%');
            });
        });

        return QueryBuilder::for(ChatAiDraft::withoutGlobalScopes()->fromSub(GetChatAiConfidenceMap::make()->cells($group, $days), 'cells'))
            ->defaultSort('-lower_bound')
            ->allowedSorts(['shop_name', 'intent', 'risk', 'decided', 'alone_clean', 'harms', 'would_send', 'lower_bound'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'intent', label: __('Customer wants'), canBeHidden: false, sortable: true)
                ->column(key: 'risk', label: __('Reply does'), canBeHidden: false, sortable: true)
                ->column(key: 'decided', label: __('Decided'), sortable: true, align: 'right')
                ->column(key: 'alone_clean', label: __('Could go alone'), tooltip: __('Sent as it was or with only a greeting or name added, and the customer was not confused or unhappy'), sortable: true, align: 'right')
                ->column(key: 'changes', label: __('Gaps filled · changed · not used'), align: 'right')
                ->column(key: 'close', label: __('Jev: close'), tooltip: __('Jev judged the draft close to what staff sent'), align: 'right')
                ->column(key: 'would_send', label: __('AI would send'), tooltip: __('Drafts the AI said it would send unseen, and how many of those staff sent as they were'), sortable: true, align: 'right')
                ->column(key: 'harms', label: __('Harms'), tooltip: __('Flagged as wrong, or the customer was confused or unhappy with the reply'), sortable: true, align: 'right')
                ->column(key: 'lower_bound', label: __('Lower bound'), tooltip: __('The lowest share of drafts that could go alone that these numbers still allow, at 95%'), sortable: true, align: 'right')
                ->column(key: 'level', label: __('Level'))
                ->defaultSort('-lower_bound');
        };
    }

    public function htmlResponse(LengthAwarePaginator $cells, ActionRequest $request): Response
    {
        $cells->getCollection()->transform(fn (ChatAiDraft $row) => $this->cellRow((object) $row->getAttributes()));

        return Inertia::render('Chat/ChatAiConfidenceMap', [
            'breadcrumbs' => ShowGroupChatAutomation::make()->getBreadcrumbs(),
            'title'       => __('AI confidence map'),
            'pageHead'    => [
                'title'         => __('AI confidence map'),
                'icon'          => ['title' => __('AI assist'), 'icon' => ['fal', 'fa-robot']],
                'subNavigation' => ShowGroupChatAutomation::make()->subNavigation($this->group),
            ],
            'days'       => (int) ($this->validatedData['days'] ?? 30),
            'dayOptions' => GetChatAiConfidenceMap::DAYS,
            'data'       => JsonResource::collection($cells),
        ])->table($this->tableStructure());
    }

    /**
     * The rows come back as drafts only to please the query builder: read the raw counts, never
     * the draft's casts.
     *
     * @return array<string, mixed>
     */
    private function cellRow(object $cell): array
    {
        return [
            'shop_name'        => $cell->shop_name,
            'intent'           => $cell->intent,
            'intent_label'     => ChatIntentEnum::tryFrom((string) $cell->intent)?->label() ?? __('Not tagged yet'),
            'topic_label'      => ChatIntentEnum::tryFrom((string) $cell->intent)?->topic()->label(),
            'risk'             => $cell->risk,
            'risk_label'       => ChatAiRiskEnum::tryFrom((string) $cell->risk)?->label() ?? __('Not tagged yet'),
            'decided'          => (int) $cell->decided,
            'alone_clean'      => (int) $cell->alone_clean,
            'gaps_filled'      => (int) $cell->gaps_filled,
            'changed'          => (int) $cell->changed,
            'not_used'         => (int) $cell->not_used,
            'close'            => (int) $cell->close,
            'would_send'       => (int) $cell->would_send,
            'would_send_right' => (int) $cell->would_send_right,
            'intent_disagree'  => (int) $cell->intent_disagree,
            'harms'            => (int) $cell->harms,
            'lag_minutes'      => $cell->lag_minutes === null ? null : (int) $cell->lag_minutes,
            'lower_bound'      => (float) $cell->lower_bound,
            'level'            => 'draft',
        ];
    }
}
