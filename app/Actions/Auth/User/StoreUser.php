<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Auth\User;

use App\Actions\Central\User\Hydrators\UserHydrateUniversalSearch;
use App\Models\Auth\Guest;
use App\Models\Auth\User;
use App\Models\HumanResources\Employee;
use App\Models\Procurement\Agent;
use App\Models\Procurement\Supplier;
use App\Rules\AlphaDashDot;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreUser
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;


    public function handle(Guest|Employee|Supplier|Agent $parent, array $objectData = []): User
    {


        $type = match (class_basename($parent)) {
            'Guest', 'Employee', 'Supplier', 'Agent' => strtolower(class_basename($parent)),
            default => null
        };

        data_set($objectData, 'type', $type);


        $user = $parent->user()->create(
            $objectData
        );

        $user->stats()->create();


        UserHydrateUniversalSearch::dispatch($user);


        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("sysadmin.edit");
    }

    public function rules(): array
    {
        return [
            'username' => ['required', new AlphaDashDot(), 'unique:App\Models\SysAdmin\SysUser,username', Rule::notIn(['export', 'create'])],
            'password' => ['required', app()->isLocal() || app()->environment('testing') ? null : Password::min(8)->uncompromised()],
            'email'    => ['required', 'email', 'unique:App\Models\SysAdmin\SysUser,email']
        ];
    }



    public function action(Guest|Employee $parent, array $objectData = []): User
    {
        $this->asAction = true;

        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($parent, $validatedData);
    }


}
