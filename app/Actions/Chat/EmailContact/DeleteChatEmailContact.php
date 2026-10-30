<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact;

use App\Actions\OrgAction;
use App\Models\Chat\ChatEmailContact;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class DeleteChatEmailContact extends OrgAction
{
    use WithChatEmailContactAuthorisation;

    private ChatEmailContact $contact;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->userCanManageEmailContact($user, $this->contact);
    }

    public function handle(ChatEmailContact $contact): void
    {
        $contact->delete();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatEmailContact $chatEmailContact, ActionRequest $request): void
    {
        $this->contact = $chatEmailContact;
        $this->initialisation($chatEmailContact->organisation, $request);

        $this->handle($chatEmailContact);
    }

    public function jsonResponse(): array
    {
        return ['deleted' => true];
    }
}
