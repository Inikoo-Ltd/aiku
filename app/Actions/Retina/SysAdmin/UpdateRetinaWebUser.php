<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-10h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaWebUser extends RetinaAction
{
    use WithActionUpdate;

    private WebUser $webUserToUpdate;

    public function handle(WebUser $webUser, array $modelData): WebUser
    {
        return UpdateWebUser::run($webUser, $modelData);
    }

    public function rules(): array
    {
        return [
            'username' => [
                'sometimes',
                'required',
                new AlphaDashDot,
                'min:4',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->webUser->website->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->webUserToUpdate->id, 'operator' => '!='],
                    ]
                ),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->webUser->website->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->webUserToUpdate->id, 'operator' => '!='],
                    ]
                ),
            ],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'required', app()->isLocal() || app()->environment('testing') ? Password::min(3) : Password::min(8)],
        ];
    }

    public function AsController(WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->webUserToUpdate = $webUser;
        $this->initialisation($request);

        return $this->handle($webUser, $this->validatedData);
    }

    public function action(WebUser $webUser, array $modelData): Webuser
    {
        $this->asAction = true;
        $this->webUserToUpdate = $webUser;
        $this->initialisationFulfilmentActions($webUser->customer->fulfilmentCustomer, $modelData);

        return $this->handle($webUser, $this->validatedData);
    }
}
