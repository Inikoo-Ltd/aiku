<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Reports\GetChatAiAcceptance;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAiDraft;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * How much of the assistant's suggestions staff take: per shop for everyone with group access,
 * and per person only for those who supervise chat on a shop (its chat managers, organisation
 * admins, group admins), limited to the shops they supervise.
 */
class IndexChatAiAcceptance extends OrgAction
{
    use WithChatAgentAuthorisation;

    public const string SHOPS = 'shops';

    public const string USERS = 'users';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    /**
     * @return Collection<int, int>
     */
    public function supervisedShopIds(User $user): Collection
    {
        setPermissionsTeamId($user->group_id);

        $shops = Shop::with('fulfilment')->where('group_id', $user->group_id)->get();

        if ($user->status && $user->hasRole(RolesEnum::GROUP_ADMIN->value)) {
            return $shops->pluck('id')->values();
        }

        return $shops->filter(fn (Shop $shop) => $this->userSupervisesChatOnShop($user, $shop))->pluck('id')->values();
    }

    public function handle(): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters(self::SHOPS);

        return QueryBuilder::for(ChatAiDraft::withoutGlobalScopes()->fromSub(GetChatAiAcceptance::make()->byShop($this->group), 'acceptance'))
            ->defaultSort('rate')
            ->allowedSorts(['shop_name', 'shop_type', 'decided', 'rate', 'ignored', 'discarded', 'rewritten'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($where) => $where->where('acceptance.shop_name', 'ilike', '%'.$value.'%')->orWhere('acceptance.shop_code', 'ilike', '%'.$value.'%')))])
            ->withPaginator(self::SHOPS, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @param  Collection<int, int>  $shopIds
     */
    public function users(Collection $shopIds): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters(self::USERS);

        return QueryBuilder::for(ChatAiDraft::withoutGlobalScopes()->fromSub(GetChatAiAcceptance::make()->byUser($this->group, $shopIds), 'acceptance'))
            ->defaultSort('rate')
            ->allowedSorts(['user_name', 'decided', 'rate', 'ignored', 'discarded', 'rewritten', 'time_lost'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($where) => $where->where('acceptance.user_name', 'ilike', '%'.$value.'%')->orWhere('acceptance.shops', 'ilike', '%'.$value.'%')))])
            ->withPaginator(self::USERS, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function columns(InertiaTable $table): void
    {
        $table
            ->column(key: 'band', label: __('Band'), tooltip: __('High: :high% or more taken. Medium: :medium% or more. No band under :min decided drafts', ['high' => GetChatAiAcceptance::HIGH, 'medium' => GetChatAiAcceptance::MEDIUM, 'min' => GetChatAiAcceptance::MIN_DRAFTS_FOR_BAND]))
            ->column(key: 'rate', label: __('Taken'), tooltip: __('Share of the drafts decided in the last :days days that staff sent as they were, with gaps filled or edited', ['days' => GetChatAiAcceptance::RATE_DAYS]), sortable: true, align: 'right')
            ->column(key: 'decided', label: __('Drafts'), tooltip: __('Drafts decided in the last :days days: waiting ones and ones replaced by a newer draft are left out', ['days' => GetChatAiAcceptance::RATE_DAYS]), sortable: true, align: 'right')
            ->column(key: 'rewritten', label: __('Rewritten'), tooltip: __('Used, then most of it changed'), sortable: true, align: 'right')
            ->column(key: 'ignored', label: __('Ignored'), tooltip: __('Staff wrote their own reply without the draft'), sortable: true, align: 'right')
            ->column(key: 'discarded', label: __('Discarded'), sortable: true, align: 'right')
            ->column(key: 'weeks', label: __('Week by week'), tooltip: __('Share taken each week, oldest first, this week last'));
    }

    public function shopsTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->name(self::SHOPS)->pageName(self::SHOPS.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('shop'), __('shops')])
                ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_type', label: __('Type'), sortable: true);
            $this->columns($table);
            $table->defaultSort('rate');
        };
    }

    public function usersTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->name(self::USERS)->pageName(self::USERS.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('person'), __('people')])
                ->column(key: 'user_name', label: __('Person'), canBeHidden: false, sortable: true)
                ->column(key: 'shops', label: __('Shops'));
            $this->columns($table);
            $table->column(key: 'time_lost', label: __('Time lost'), tooltip: __('Drafts this person ignored or discarded and then retyped, in the last :days days: Jev judged their own reply to say the same as the draft. Minutes: active time they spent writing those replies, measured in the inbox since 19 Nov 2026', ['days' => GetChatAiAcceptance::RATE_DAYS]), sortable: true, align: 'right');
            $table->defaultSort('rate');
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $row, array $link): array
    {
        $weeks = [];
        foreach (GetChatAiAcceptance::weekStarts() as $weeksAgo => $start) {
            $decided = (int) $row->{"d$weeksAgo"};
            $adopted = (int) $row->{"a$weeksAgo"};
            $weeks[] = [
                'start'   => $start->toDateString(),
                'decided' => $decided,
                'rate'    => $decided ? (int) round(100 * $adopted / $decided) : null,
                'band'    => GetChatAiAcceptance::band($adopted, $decided, GetChatAiAcceptance::MIN_DRAFTS_FOR_WEEK),
                'url'     => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['kind' => 'draft', 'week' => $start->toDateString()]]),
            ];
        }

        return [
            'decided'   => (int) $row->decided,
            'rate'      => $row->rate === null ? null : (int) $row->rate,
            'band'      => GetChatAiAcceptance::band((int) $row->adopted, (int) $row->decided),
            'rewritten' => (int) $row->rewritten,
            'ignored'   => (int) $row->ignored,
            'discarded' => (int) $row->discarded,
            'weeks'     => array_reverse($weeks),
            'url'       => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['kind' => 'draft']]),
            'urls'      => [
                'rewritten' => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['outcome' => 'rewritten']]),
                'ignored'   => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['outcome' => 'own_reply']]),
                'discarded' => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['outcome' => 'discarded']]),
                'time_lost' => route('grp.chat.ai.aiku_drafts', ['filter' => $link + ['flag' => 'time_lost']]),
            ],
        ];
    }

    /**
     * Only the page: the raw rows carry who decided each draft and the time lost, which the page shows
     * on supervised shops only.
     */
    public function jsonResponse(): never
    {
        abort(404);
    }

    public function htmlResponse(LengthAwarePaginator $shops, ActionRequest $request): Response
    {
        $shops->getCollection()->transform(function (ChatAiDraft $draft) {
            $row = (object) $draft->getAttributes();

            return [
                'shop_name' => $row->shop_name,
                'shop_code' => $row->shop_code,
                'shop_type' => $row->shop_type,
            ] + $this->row($row, ['shop' => $row->shop_slug]);
        });

        $supervised = $this->supervisedShopIds($request->user());
        $users      = null;
        if ($supervised->isNotEmpty()) {
            $users = $this->users($supervised);
            $users->getCollection()->transform(function (ChatAiDraft $draft) {
                $row = (object) $draft->getAttributes();

                return [
                    'user_name' => $row->user_name,
                    'shops'     => $row->shops,
                    'time_lost' => (int) $row->time_lost,
                    'time_lost_minutes' => (int) round($row->time_lost_seconds / 60),
                ] + $this->row($row, ['staff' => (string) $row->user_id]);
            });
        }

        $title    = __('Suggestions taken');
        $response = Inertia::render('Chat/ChatAiAcceptance', [
            'breadcrumbs' => ShowGroupChatAutomation::make()->getBreadcrumbs(),
            'title'       => $title,
            'pageHead'    => [
                'title'         => $title,
                'icon'          => ['title' => __('AI assist'), 'icon' => ['fal', 'fa-robot']],
                'subNavigation' => ShowGroupChatAutomation::make()->subNavigation($this->group),
            ],
            'summary'     => GetChatAiAcceptance::run($this->group),
            'shops'       => JsonResource::collection($shops),
            'users'       => $users ? JsonResource::collection($users) : null,
        ])->table($this->shopsTableStructure());

        return $users ? $response->table($this->usersTableStructure()) : $response;
    }
}
