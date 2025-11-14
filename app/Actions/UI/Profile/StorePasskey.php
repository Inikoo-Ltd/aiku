<?php

/*
 * Author: [Your Name] <[Your Email]>
 * Created: [Current Date]
 * Copyright (c) [Current Year], [Your Company]
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class StorePasskey extends OrgAction
{
    public function handle(User $user, array $passkeyData): array
    {
        $user = auth()->user();
        $storePasskeyAction = app(StorePasskeyAction::class);

        try {
            $storePasskeyAction->execute(
                $user,
                $passkeyData['passkey'],
                $passkeyData['options'],
                request()->getHost(),
                ['name' => Str::random(10)],
            );

            return [
                'success' => true,
                'message' => __('Passkey successfully added')
            ];
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'name' => __('passkeys::passkeys.error_something_went_wrong_generating_the_passkey'),
            ]);
        }

    }

    public function rules(): array
    {
        return [
            'passkey' => 'required|json',
            'options' => 'required|json',
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        $validated = $request->validate($this->rules());

        return $this->handle($request->user(), $validated);
    }
}
