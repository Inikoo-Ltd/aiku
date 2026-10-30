<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026 14:12:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\SavedReply;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSavedReply;
use App\Models\SysAdmin\User;

trait WithChatSavedReplyAuthorisation
{
    use WithChatAgentAuthorisation;

    /**
     * A personal reply is its owner's alone. A shared one is kept by whoever supervises chat on
     * its shop, or by an organisation admin when it is shared across every shop.
     */
    protected function userMayManageSavedReply(User $user, ChatSavedReply $savedReply): bool
    {
        if (!$user->status) {
            return false;
        }

        if ($savedReply->isPersonal()) {
            return $savedReply->user_id === $user->id;
        }

        if ($savedReply->shop_id === null) {
            return $this->userAdministersOrganisationChat($user, $savedReply->organisation_id);
        }

        $shop = Shop::find($savedReply->shop_id);

        return $shop instanceof Shop && $this->userSupervisesChatOnShop($user, $shop);
    }

    protected function userAdministersOrganisationChat(User $user, int $organisationId): bool
    {
        return $user->status && $user->authTo(["org-admin.{$organisationId}"]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function savedReplyResource(ChatSavedReply $savedReply, User $user): array
    {
        return [
            'id'          => $savedReply->id,
            'title'       => $savedReply->title,
            'body'        => $savedReply->body,
            'is_personal' => $savedReply->isPersonal(),
            'shop_id'     => $savedReply->shop_id,
            'can_manage'  => $this->userMayManageSavedReply($user, $savedReply),
        ];
    }
}
