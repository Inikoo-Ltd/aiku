<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatEmailContact;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * The saved contacts offered while the agent types who a new email is to: the shop's own and
 * the organisation's shared ones, never another organisation's.
 */
class SearchChatEmailContacts extends OrgAction
{
    use WithChatEmailContactAuthorisation;

    public const int LIMIT = 10;

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
     * @return array<int, array{id: int, name: string, email: string, company: ?string, note: ?string, all_shops: bool}>
     */
    public function handle(Shop $shop, ?string $search): array
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
            ->map(fn (ChatEmailContact $contact) => self::emailContactData($contact))
            ->all();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, Arr::get($this->validatedData, 'query'));
    }

    public function jsonResponse(array $contacts): array
    {
        return ['data' => $contacts];
    }
}
