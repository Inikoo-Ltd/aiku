<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact;

use App\Actions\Chat\ChatSession\StartCustomerEmailChat;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatEmailContact;
use App\Models\SysAdmin\User;

trait WithChatEmailContactAuthorisation
{
    use WithChatAgentAuthorisation;

    /**
     * Whoever may start a new email on the shop may look its saved contacts up.
     */
    protected function userCanLookUpEmailContactsOnShop(User $user, Shop $shop): bool
    {
        return $this->userCanActOnChatOnShop($user, $shop)
            || StartCustomerEmailChat::userCanEmailCustomersOnShop($user, $shop);
    }

    /**
     * Writing to an address that is not a customer is the chat inbox's work, so saving and
     * changing contacts is too. A contact offered on every shop is shared by everybody who
     * works chat in the organisation.
     */
    protected function userCanManageEmailContact(User $user, ChatEmailContact $contact): bool
    {
        if ($contact->shop) {
            return $this->userCanActOnChatOnShop($user, $contact->shop);
        }

        return $this->userCanWorkChatOnOrganisation($user, $contact->organisation);
    }

    /**
     * @return array{id: int, name: string, email: string, company: ?string, note: ?string, all_shops: bool}
     */
    public static function emailContactData(ChatEmailContact $contact): array
    {
        return [
            'id'        => $contact->id,
            'name'      => $contact->name,
            'email'     => $contact->email,
            'company'   => $contact->company,
            'note'      => $contact->note,
            'all_shops' => $contact->shop_id === null,
        ];
    }

    private function emailContactIsTaken(int $organisationId, ?int $shopId, string $email, ?int $exceptId = null): bool
    {
        return ChatEmailContact::where('organisation_id', $organisationId)
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId), fn ($query) => $query->whereNull('shop_id'))
            ->where('email', self::normalisedEmail($email))
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public static function normalisedEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
