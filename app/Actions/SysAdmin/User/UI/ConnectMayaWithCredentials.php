<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 17:06:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ConnectMayaWithCredentials
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(User $user, array $modelData): array
    {
        return [
            'token' => $user->createToken(Arr::get($modelData, 'device_name', 'unknown-device'))->plainTextToken,
        ];
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'exists:users,username'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $user = User::where('username', $request->get('username'))->first();

        if (! $user) {
            $validator->errors()->add('username', __('Wrong username.'));

            return;
        }

        if (! $user->status) {
            $validator->errors()->add('username', __('User is not active.'));

            return;
        }

        if (! Hash::check($this->get('password'), $user->password)) {

            $validator->errors()->add('password', __('Wrong password.'));

        }

    }

    public function asController(ActionRequest $request): array
    {

        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();
        $user = User::where('username', $this->get('username'))->first();

        return $this->handle($user, Arr::only($validatedData, ['device_name']));
    }
}
