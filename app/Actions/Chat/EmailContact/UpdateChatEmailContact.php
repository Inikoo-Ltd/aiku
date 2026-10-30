<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact;

use App\Actions\OrgAction;
use App\Models\Chat\ChatEmailContact;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateChatEmailContact extends OrgAction
{
    use WithChatEmailContactAuthorisation;

    private ChatEmailContact $contact;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user instanceof User && $this->userCanManageEmailContact($user, $this->contact);
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'required', 'string', 'max:255'],
            'email'   => ['sometimes', 'required', 'string', 'email', 'max:255', $this->uniqueEmailRule()],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note'    => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    private function uniqueEmailRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->emailContactIsTaken($this->contact->organisation_id, $this->contact->shop_id, (string) $value, $this->contact->id)) {
                $fail(__('This address is already a saved contact.'));
            }
        };
    }

    public function handle(ChatEmailContact $contact, array $modelData): ChatEmailContact
    {
        if (Arr::has($modelData, 'email')) {
            $modelData['email'] = self::normalisedEmail($modelData['email']);
        }

        if (Arr::has($modelData, 'name')) {
            $modelData['name'] = trim($modelData['name']);
        }

        $contact->update($modelData);

        return $contact;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatEmailContact $chatEmailContact, ActionRequest $request): ChatEmailContact
    {
        $this->contact = $chatEmailContact;
        $this->initialisation($chatEmailContact->organisation, $request);

        return $this->handle($chatEmailContact, $this->validatedData);
    }

    public function action(ChatEmailContact $contact, array $modelData): ChatEmailContact
    {
        $this->asAction = true;
        $this->contact  = $contact;
        $this->initialisation($contact->organisation, $modelData);

        return $this->handle($contact, $this->validatedData);
    }

    public function jsonResponse(ChatEmailContact $contact): array
    {
        return ['data' => self::emailContactData($contact)];
    }
}
