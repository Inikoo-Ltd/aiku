<?php

/*
 * Author: [Your Name] <[Your Email]>
 * Created: [Current Date]
 * Copyright (c) [Current Year], [Your Company]
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StorePasskey extends OrgAction
{
    public function handle(User $user, array $passkeyData): array
    {
        return DB::transaction(function () use ($user, $passkeyData) {
            $passkey = $user->passkeys()->create([
                'name' => $passkeyData['name'] ?? 'Passkey ' . ($user->passkeys()->count() + 1),
                'credential_id' => $passkeyData['credential_id'],
                'data' => [
                    'public_key' => $passkeyData['public_key'],
                    'counter' => $passkeyData['counter'] ?? 0,
                    'transports' => $passkeyData['transports'] ?? [],
                ],
                'last_used_at' => now(),
            ]);

            return [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'credential_id' => $passkey->credential_id,
                'last_used_at' => $passkey->last_used_at,
                'created_at' => $passkey->created_at,
            ];
        });
    }
    
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'credential_id' => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'counter' => ['sometimes', 'integer', 'min:0'],
            'transports' => ['sometimes', 'array'],
            'transports.*' => ['string'],
        ];
    }
    
    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);
        
        $validated = $request->validate($this->rules());
        
        return $this->handle($request->user(), $validated);
    }
}
