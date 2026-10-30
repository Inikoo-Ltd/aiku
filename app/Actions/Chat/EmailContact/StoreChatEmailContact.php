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
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;

/**
 * Saves an address the inbox writes to, such as a carrier or the accountants, for the shop or,
 * with all_shops, for every shop of its organisation.
 */
class StoreChatEmailContact extends OrgAction
{
    use WithChatEmailContactAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user instanceof User && $this->userCanActOnChatOnShop($user, $this->shop);
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', $this->uniqueEmailRule()],
            'company'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'note'      => ['sometimes', 'nullable', 'string', 'max:2000'],
            'all_shops' => ['sometimes', 'boolean'],
        ];
    }

    private function uniqueEmailRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $shopId = $this->get('all_shops') ? null : $this->shop->id;

            if ($this->emailContactIsTaken($this->organisation->id, $shopId, (string) $value)) {
                $fail(__('This address is already a saved contact.'));
            }
        };
    }

    public function handle(Shop $shop, array $modelData): ChatEmailContact
    {
        return ChatEmailContact::create([
            'organisation_id'    => $shop->organisation_id,
            'shop_id'            => Arr::get($modelData, 'all_shops') ? null : $shop->id,
            'name'               => trim($modelData['name']),
            'email'              => self::normalisedEmail($modelData['email']),
            'company'            => Arr::get($modelData, 'company'),
            'note'               => Arr::get($modelData, 'note'),
            'created_by_user_id' => Arr::get($modelData, 'created_by_user_id', Auth::id()),
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Shop $shop, ActionRequest $request): ChatEmailContact
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function action(Shop $shop, array $modelData): ChatEmailContact
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function jsonResponse(ChatEmailContact $contact): array
    {
        return ['data' => self::emailContactData($contact)];
    }
}
