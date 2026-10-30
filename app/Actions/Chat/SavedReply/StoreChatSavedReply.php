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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Anybody answering a shop's chats keeps replies of their own. Sharing one with the team is a
 * supervisor's call, and sharing it across every shop of the organisation an admin's.
 */
class StoreChatSavedReply
{
    use AsAction;
    use WithChatSavedReplyAuthorisation;

    /**
     * @param  array{title: string, body: string}  $modelData
     */
    public function handle(User $user, Shop $shop, array $modelData, bool $isShared = false, bool $acrossShops = false): ChatSavedReply
    {
        return ChatSavedReply::create([
            'group_id'           => $shop->group_id,
            'organisation_id'    => $shop->organisation_id,
            'shop_id'            => $isShared && !$acrossShops ? $shop->id : null,
            'user_id'            => $isShared ? null : $user->id,
            'created_by_user_id' => $user->id,
            'title'              => $modelData['title'],
            'body'               => $modelData['body'],
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id'      => ['required', 'integer'],
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['required', 'string', 'max:20000'],
            'is_shared'    => ['sometimes', 'boolean'],
            'across_shops' => ['sometimes', 'boolean'],
        ]);

        $user        = $request->user();
        $shop        = Shop::find($validated['shop_id']);
        $isShared    = (bool) ($validated['is_shared'] ?? false);
        $acrossShops = $isShared && (bool) ($validated['across_shops'] ?? false);

        if (!$user instanceof User || !$shop instanceof Shop || !$this->userCanActOnChatOnShop($user, $shop)) {
            return response()->json(['message' => __('You may not use saved replies on this shop')], 403);
        }

        if ($isShared && !$this->userSupervisesChatOnShop($user, $shop)) {
            return response()->json(['message' => __('Only a supervisor may share a reply with the team')], 403);
        }

        if ($acrossShops && !$this->userAdministersOrganisationChat($user, $shop->organisation_id)) {
            return response()->json(['message' => __('Only an organisation admin may share a reply across every shop')], 403);
        }

        $savedReply = $this->handle($user, $shop, $validated, $isShared, $acrossShops);

        return response()->json(['data' => $this->savedReplyResource($savedReply, $user)], 201);
    }
}
