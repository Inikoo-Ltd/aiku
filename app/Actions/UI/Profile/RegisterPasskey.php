<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Exceptions\InvalidPasskey;
use Spatie\LaravelPasskeys\Exceptions\InvalidPasskeyOptions;

class RegisterPasskey
{
    use AsController;

    public function handle(ActionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            app(StorePasskeyAction::class)->execute(
                $user,
                $request->validated('passkey'),
                $request->validated('options'),
                $request->getHost(),
                ['name' => $request->input('name', 'Passkey '.now()->format('Y-m-d H:i'))],
            );
        } catch (InvalidPasskey|InvalidPasskeyOptions) {
            throw ValidationException::withMessages([
                'passkey' => trans('Something went wrong while registering the passkey.'),
            ]);
        }

        return redirect()->back();
    }

    public function rules(): array
    {
        return [
            'passkey' => ['required', 'json'],
            'options' => ['required', 'json'],
            'name'    => ['nullable', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        return $this->handle($request);
    }
}
