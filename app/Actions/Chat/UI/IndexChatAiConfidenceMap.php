<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Reports\GetChatAiClaims;
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
            ->allowedSorts(['shop_name', 'intent', 'risk', 'decided', 'alone_clean', 'use_rewrote', 'harms', 'would_send', 'lower_bound'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public const string CLAIMS = 'claims';

    public const string CLAIMERS = 'claimers';

    /**
     * The damaged, missing and wrong item claims, one row per shop and value band.
     */
    public function claims(Group $group, int $days): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters(self::CLAIMS);

        return QueryBuilder::for(ChatAiDraft::withoutGlobalScopes()->fromSub(GetChatAiClaims::make()->byShopAndBand($group, $days), 'claims'))
            ->defaultSort('shop_name')
            ->allowedSorts(['shop_name', 'band', 'cases', 'as_proposed', 'differently', 'declined', 'open', 'accepted'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where('claims.shop_name', 'ilike', '%'.$value.'%'))])
            ->withPaginator(self::CLAIMS, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function claimers(Group $group, int $days): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters(self::CLAIMERS);

        return QueryBuilder::for(ChatAiDraft::withoutGlobalScopes()->fromSub(GetChatAiClaims::make()->repeatClaimers($group, $days), 'claimers'))
            ->defaultSort('-cases')
            ->allowedSorts(['customer_name', 'shop_name', 'cases', 'settled', 'settled_value'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($where) => $where->where('claimers.customer_name', 'ilike', '%'.$value.'%')->orWhere('claimers.shop_name', 'ilike', '%'.$value.'%')))])
            ->withPaginator(self::CLAIMERS, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function claimsTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name(self::CLAIMS)->pageName(self::CLAIMS.'Page')
                ->withGlobalSearch()
                ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'band', label: __('Value'), tooltip: __('What the claim was worth, net, in the group currency: what the draft proposed, else what was given'), canBeHidden: false, sortable: true)
                ->column(key: 'cases', label: __('Claims'), sortable: true, align: 'right')
                ->column(key: 'as_proposed', label: __('As proposed'), tooltip: __('Staff gave what the draft proposed, the same remedy for about the same amount'), sortable: true, align: 'right')
                ->column(key: 'differently', label: __('Differently'), tooltip: __('Staff settled it another way or for another amount'), sortable: true, align: 'right')
                ->column(key: 'declined', label: __('Nothing done'), tooltip: __('Nothing was given on the order within 7 days'), sortable: true, align: 'right')
                ->column(key: 'open', label: __('Open'), tooltip: __('Less than 7 days ago'), sortable: true, align: 'right')
                ->column(key: 'jev', label: __('Jev: reply agrees'), tooltip: __('Jev read staff reply as settling it the way the draft proposed, out of the replies that settle or refuse it'), align: 'right')
                ->column(key: 'accepted', label: __('Accepted as proposed'), tooltip: __('Share of the claims no longer open settled as the draft proposed'), sortable: true, align: 'right')
                ->defaultSort('shop_name');
        };
    }

    public function claimersTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name(self::CLAIMERS)->pageName(self::CLAIMERS.'Page')
                ->withGlobalSearch()
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_name', label: __('Shop'), sortable: true)
                ->column(key: 'cases', label: __('Claims'), sortable: true, align: 'right')
                ->column(key: 'settled', label: __('Settled'), sortable: true, align: 'right')
                ->column(key: 'settled_value', label: __('Given'), tooltip: __('Net, in the group currency'), sortable: true, align: 'right')
                ->defaultSort('-cases');
        };
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
                ->column(key: 'use_rewrote', label: __('Used, then rewrote'), tooltip: __('Staff pressed Use and then changed most of it or replaced it: a draft they wanted but could not send. Copied: staff copied the text instead of pressing Use'), sortable: true, align: 'right')
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

        $days     = (int) ($this->validatedData['days'] ?? 30);
        $claims   = $this->claims($this->group, $days);
        $claimers = $this->claimers($this->group, $days);
        $numbers  = fn (ChatAiDraft $row) => array_map(fn ($value) => is_numeric($value) ? $value + 0 : $value, $row->getAttributes());
        $claims->getCollection()->transform($numbers);
        $claimers->getCollection()->transform($numbers);

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
            'currency'   => $this->group->currency->code,
            'claimBands' => GetChatAiClaims::make()->byBand($this->group, $days),
            'claims'     => JsonResource::collection($claims),
            'claimers'   => JsonResource::collection($claimers),
        ])->table($this->tableStructure())->table($this->claimsTableStructure())->table($this->claimersTableStructure());
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
            'use_rewrote'      => (int) $cell->use_rewrote,
            'copied'           => (int) $cell->copied,
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
