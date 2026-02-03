<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\RetinaAction;
use App\Models\CRM\WebUserPasswordReset;
use Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Validation\Validator;

class ShowRetinaResetWebUserPassword extends RetinaAction
{
    use AsController;

    private \Illuminate\Support\MessageBag $errors;

    private null|WebUserPasswordReset $webUserPasswordReset;

    public function handle(array $modelData): Response
    {

        $webUserPasswordReset = WebUserPasswordReset::find($modelData['id']);

        return Inertia::render(
            'Auth/ResetWebUserPassword',
            [
            'token' => $modelData['token'],
            'webUserPasswordResetID' => $webUserPasswordReset->id,
            'username' => $webUserPasswordReset->webUser->username
        ]
        );
    }



    public function getValidationRedirect(): string
    {



        $queryString = http_build_query(['errors' => $this->errors->messages()]);


        return route('retina.reset-password.error').'?errors='.$queryString;
    }


    public function rules(): array
    {
        return [
            'token' => ['required', 'string','min:24','max:28'],
            'id'    => ['required', 'integer',Rule::exists('web_user_password_resets', 'id')->where('website_id', $this->website->id)]
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $webUserPasswordReset = WebUserPasswordReset::find($request->input('id'));

        if (!$webUserPasswordReset) {
            $validator->errors()->add('id', 'token not found.');
        } else {
            if (!Hash::check($this->get('token'), $webUserPasswordReset->token)) {
                $validator->errors()->add('token', 'Invalid token.');
            }
            if ($webUserPasswordReset->created_at->addMinutes(30)->isPast()) {
                $validator->errors()->add('token', __('Token expired, the token is only valid for 30 minutes.'));
            }
        }



        $this->errors = $validator->errors();

    }
    public function asController(ActionRequest $request): Response
    {
        $this->logoutInitialisation($request);
        return $this->handle($this->validatedData);

    }


}
