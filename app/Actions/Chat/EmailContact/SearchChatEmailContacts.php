<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatEmailContact;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The saved contacts offered while the agent types who a new email is to: the shop's own and
 * the organisation's shared ones, never another organisation's.
 */
class SearchChatEmailContacts extends OrgAction
{
    use WithChatEmailContactAuthorisation;

    public const int LIMIT = 10;

    public const int RECENT_DAYS = 90;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->userCanLookUpEmailContactsOnShop($user, $this->shop);
    }

    public function rules(): array
    {
        return [
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, company: ?string, note: ?string, all_shops: bool, can_manage?: bool}>
     */
    public function handle(Shop $shop, ?string $search, ?User $viewer = null): array
    {
        $search = trim((string) $search);

        return ChatEmailContact::offeredOnShop($shop)
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($query) => $query->whereLike('name', $pattern)
                    ->orWhereLike('email', $pattern)
                    ->orWhereLike('company', $pattern));
            })
            ->orderByRaw('shop_id is null')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ChatEmailContact $contact) => $viewer
                ? [...self::emailContactData($contact), 'can_manage' => $this->userCanManageEmailContact($viewer, $contact)]
                : self::emailContactData($contact))
            ->all();
    }

    /**
     * HELP-4553: the addresses this shop has emailed or been emailed from lately, newest first,
     * so an address written to this morning is offered without being saved as a contact.
     *
     * @return array<int, array{name: ?string, email: string}>
     */
    public function recentAddresses(Shop $shop, ?string $search): array
    {
        $search     = trim((string) $search);
        $lastActive = 'greatest(created_at, last_agent_message_at, last_visitor_message_at)';

        return ChatSession::query()
            ->where('shop_id', $shop->id)
            ->where('channel', ChatChannelEnum::EMAIL)
            ->whereRaw("$lastActive >= ?", [now()->subDays(self::RECENT_DAYS)])
            ->where('is_spam', false)
            ->where('is_rubbish', false)
            ->whereRaw("coalesce(metadata->>'email', '') like '%@%'")
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($query) => $query->whereRaw("metadata->>'email' ilike ?", [$pattern])
                    ->orWhereRaw("metadata->>'name' ilike ?", [$pattern]));
            })
            ->whereNotIn(DB::raw("lower(metadata->>'email')"), ChatEmailContact::offeredOnShop($shop)->selectRaw('lower(email)'))
            ->selectRaw("lower(metadata->>'email') as recent_email, (array_agg(metadata->>'name' order by $lastActive desc) filter (where metadata->>'name' not like '%@%'))[1] as recent_name, max($lastActive) as last_at")
            ->groupByRaw("lower(metadata->>'email')")
            ->orderByDesc('last_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ChatSession $session) => [
                'name'  => $session->recent_name,
                'email' => $session->recent_email,
            ])
            ->all();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        $search   = Arr::get($this->validatedData, 'query');
        $contacts = $this->handle($shop, $search, $request->user());

        return [
            'data'   => $contacts,
            'recent' => $this->recentAddresses($shop, $search),
        ];
    }

    /**
     * @param  array{data: array<int, mixed>, recent: array<int, mixed>}  $result
     */
    public function jsonResponse(array $result): array
    {
        return $result;
    }
}
