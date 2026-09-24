<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Json;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * Before an agent saves somebody they are writing to as a prospect, whether the address is one
 * already, and whose, so the same person is never brought in twice.
 */
class GetProspectByEmail extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->userCanActOnChatOnShop($user, $this->shop);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{id: int, name: ?string, company_name: ?string, owner: ?string}|null
     */
    public function handle(Shop $shop, string $email): ?array
    {
        $prospect = Prospect::where('shop_id', $shop->id)->where('email', trim($email))->with('user:id,contact_name')->first();

        return $prospect ? [
            'id'           => $prospect->id,
            'name'         => $prospect->contact_name ?? $prospect->name,
            'company_name' => $prospect->company_name,
            'owner'        => $prospect->user?->contact_name,
        ] : null;
    }

    public function asController(Shop $shop, ActionRequest $request): ?array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['email']);
    }

    public function jsonResponse(?array $prospect): array
    {
        return ['prospect' => $prospect];
    }
}
