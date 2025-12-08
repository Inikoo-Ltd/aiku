<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Oct 2023 15:27:31 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserPassword extends GrpAction
{
    use WithActionUpdate;

    public function handle(User $user, array $modelData): User
    {
        data_set($modelData, 'reset_password', false);

        return $this->update($user, $modelData, 'settings');
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                app()->isLocal() || app()->environment('testing') ? null : Password::min(8),
            ],
        ];
    }

    public function asController(ActionRequest $request): User
    {
        $this->initialisation($request->user()->group, $request);

        return $this->handle($request->user(), $this->validatedData);
    }

    public function action(User $user, $objectData): User
    {
        $this->asAction = true;
        $this->initialisation($user->group, $objectData);

        return $this->handle($user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        Session::put('reloadLayout', '1');

        return Redirect::route('grp.dashboard.show');
    }

    public string $commandSignature = 'user:password {slug : User slug} {password : New password}';

    public function asCommand(Command $command): int
    {
        try {
            /** @var User $user */
            $user = User::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('User not found');

            return 1;
        }

        $this->action(
            $user,
            ['password' => $command->argument('password')]
        );

        $command->info('Password updated');

        return 0;
    }
}
