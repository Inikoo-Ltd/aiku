<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026 14:12:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\SavedReply;

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSavedReply;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The saved replies somebody may paste while answering a shop: their own, and the ones shared
 * on that shop or across its organisation.
 */
class IndexChatSavedReplies
{
    use AsAction;
    use WithChatSavedReplyAuthorisation;

    /**
     * @return Collection<int, ChatSavedReply>
     */
    public function handle(User $user, Shop $shop, ?string $search = null): Collection
    {
        return ChatSavedReply::query()
            ->availableTo($user, $shop)
            ->when($search, function ($query, string $search) {
                $query->where(fn ($match) => $match->whereLike('title', "%{$search}%")->orWhereLike('body', "%{$search}%"));
            })
            ->orderBy('title')
            ->limit(500)
            ->get();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'integer'],
            'search'  => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();
        $shop = Shop::find($validated['shop_id']);

        if (!$user instanceof User || !$shop instanceof Shop || !$this->userCanActOnChatOnShop($user, $shop)) {
            return response()->json(['message' => __('You may not use saved replies on this shop')], 403);
        }

        return response()->json([
            'data'                  => $this->handle($user, $shop, $validated['search'] ?? null)
                ->map(fn (ChatSavedReply $savedReply) => $this->savedReplyResource($savedReply, $user))
                ->values(),
            'can_share'             => $this->userSupervisesChatOnShop($user, $shop),
            'can_share_across_shops' => $this->userAdministersOrganisationChat($user, $shop->organisation_id),
        ]);
    }
}
