<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Profile;

use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\UI\WithProfile;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaProfile extends RetinaAction
{
    use WithActionUpdate;
    use WithProfile;

    public function handle(WebUser $webUser, array $modelData): WebUser
    {
        return UpdateWebUser::run($webUser, $modelData);
    }


    public function rules(): array
    {
        $rules = [
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'        => [
                'sometimes',
                'required',
                'email',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->website->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                )
            ],
            'about'        => 'sometimes|nullable|string|max:255',
            'image'        => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],

            'username' => [
                'sometimes',
                'required',
                'min:4',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->website->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                )
            ],
            'password' => ['sometimes', 'required', app()->isLocal() || app()->environment('testing') ? Password::min(4) : Password::min(8)],


            'language_id' => ['sometimes', 'required', 'exists:languages,id'],


        ];

        if ($this->webUser->is_root) {
            $rules['company_name']    = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['phone']           = ['sometimes', 'nullable', new Phone()];
            $rules['contact_address'] = ['sometimes', 'required', new ValidAddress()];
        }


        return $rules;
    }


    public function asController(ActionRequest $request): WebUser
    {
        $this->initialisation($request);

        return $this->handle($this->webUser, $this->validatedData);
    }

    public function action(WebUser $webUser, array $modelData): WebUser
    {
        $this->asAction = true;
        $this->website = $webUser->website;
        $this->webUser = $webUser;
        $this->setRawAttributes($modelData);

        return $this->handle($webUser, $this->validateAttributes());
    }
}
