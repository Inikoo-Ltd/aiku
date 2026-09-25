<?php

/*
 * author Arya Permana - Kirin
 * created on 20-01-2025-11h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\WebUser\DeleteWebUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\CRM\WebUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaWebUser extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels {
        authorize as customerOwnsRouteModels;
    }
    use WithActionUpdate;

    private WebUser $webUserToDelete;

    public function handle(WebUser $webUser): void
    {
        DeleteWebUser::run($webUser, true);
    }


    public function authorize(ActionRequest $request): bool
    {
        if (!$this->customerOwnsRouteModels($request)) {
            return false;
        }

        return $this->asAction || $request->user()->is_root;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->webUserToDelete->is_root) {
            $validator->errors()->add('web_user', __('The main user can not be deleted'));
        }
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.sysadmin.web-users.index');
    }

    public function asController(WebUser $webUser, ActionRequest $request): void
    {
        $this->webUserToDelete = $webUser;
        $this->initialisation($request);

        $this->handle($webUser);
    }

    public function action(WebUser $webUser): void
    {
        $this->asAction        = true;
        $this->webUserToDelete = $webUser;
        $this->initialisationFulfilmentActions($webUser->customer->fulfilmentCustomer, []);

        $this->handle($webUser);
    }

}
