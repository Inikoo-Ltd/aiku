<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Helpers\AI\UI\ShowAiDashboard;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAuraQuestion;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * What customer service asked Aura from the reply box and what she answered (INI-519), newest first,
 * so we can review what staff ask and feed it into her draft brief. The rows name the agent who
 * asked, so only the shops the viewer supervises are listed, as for the per-person suggestion figures.
 */
class IndexChatAuraQuestions extends OrgAction
{
    use WithChatAgentAuthorisation;

    /** @var Collection<int, int> */
    private Collection $shopIds;

    public function authorize(ActionRequest $request): bool
    {
        $this->shopIds = IndexChatAiAcceptance::make()->supervisedShopIds($request->user());

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
    public function handle(Collection $shopIds): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->where('chat_aura_questions.question', 'ilike', '%'.$value.'%')
                    ->orWhere('chat_aura_questions.answer', 'ilike', '%'.$value.'%')
                    ->orWhere('users.contact_name', 'ilike', '%'.$value.'%');
            });
        });

        $query = ChatAuraQuestion::query()
            ->join('shops', 'shops.id', '=', 'chat_aura_questions.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->join('users', 'users.id', '=', 'chat_aura_questions.user_id')
            ->leftJoin('chat_sessions', 'chat_sessions.id', '=', 'chat_aura_questions.chat_session_id')
            ->whereIn('chat_aura_questions.shop_id', $shopIds)
            ->select([
                'chat_aura_questions.*',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                'users.contact_name as asked_by',
                'chat_sessions.ulid as session_ulid',
                DB::raw("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from', chat_sessions.metadata->>'email') as contact"),
            ]);

        return QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'shop_name', 'asked_by', 'cost'])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('shop', fn ($query, $value) => $query->where('shops.slug', $value)),
                AllowedFilter::callback('staff', fn ($query, $value) => $query->where('chat_aura_questions.user_id', (int) $value)),
                AllowedFilter::callback('redrafted', fn ($query, $value) => $value === 'yes' ? $query->whereNotNull('chat_aura_questions.redrafted_at') : $query->whereNull('chat_aura_questions.redrafted_at')),
            ])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->selectFilter('shop', Shop::whereIn('id', $this->shopIds)->orderBy('name')->pluck('name', 'slug')->all(), __('Shop'));
            $staff = DB::table('chat_aura_questions')->join('users', 'users.id', 'chat_aura_questions.user_id')
                ->whereIn('chat_aura_questions.shop_id', $this->shopIds)
                ->distinct()->orderBy('users.contact_name')
                ->pluck('users.contact_name', 'users.id')
                ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
            if ($staff) {
                $table->selectFilter('staff', $staff, __('Asked by'));
            }
            $table->selectFilter('redrafted', ['yes' => __('Redrafted with it'), 'no' => __('Not redrafted')], __('Redraft'));

            $table
                ->withGlobalSearch()
                ->column(key: 'created_at', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_name', label: __('Shop'), sortable: true)
                ->column(key: 'asked_by', label: __('Asked by'), sortable: true)
                ->column(key: 'contact', label: __('Customer'))
                ->column(key: 'question', label: __('Question'), canBeHidden: false)
                ->column(key: 'answer', label: __('Aura answered'), canBeHidden: false)
                ->column(key: 'lookups', label: __('Looked up'))
                ->column(key: 'cost', label: __('Cost'), sortable: true, align: 'right')
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(): never
    {
        abort(404);
    }

    public function htmlResponse(LengthAwarePaginator $questions, ActionRequest $request): Response
    {
        $questions->getCollection()->transform(fn (ChatAuraQuestion $question) => [
            'created_at' => Carbon::parse($question->created_at)->toIso8601String(),
            'shop_name'  => $question->shop_name,
            'asked_by'   => $question->asked_by,
            'contact'    => $question->contact,
            'question'   => $question->question,
            'answer'     => $question->answer,
            'answered'   => $question->answered_at !== null,
            'failed'     => in_array('no_answer', $question->flags, true),
            'redrafted'  => $question->redrafted_at !== null,
            'lookups'    => collect($question->tool_calls)->pluck('name')->countBy()->map(fn (int $times, string $name) => $times > 1 ? "$name ×$times" : $name)->values()->all(),
            'cost'       => (float) $question->cost,
            'url'        => $question->session_ulid ? route('grp.org.chat.inbox.conversation', [$question->organisation_slug, trim((string) $question->session_ulid)]) : null,
        ]);

        return Inertia::render('Ai/AuraQuestions', [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'title'       => __('Ask Aura'),
            'pageHead'    => [
                'title' => __('Ask Aura'),
                'model' => __('AI'),
                'icon'  => ['title' => 'Aura', 'icon' => ['fal', 'fa-comment-dots']],
            ],
            'data' => JsonResource::collection($questions),
        ])->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...ShowAiDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.ai.aura.questions', 'parameters' => []],
                    'label' => __('Ask Aura'),
                ],
            ],
        ];
    }
}
