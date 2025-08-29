<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithProfile;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Models\CRM\WebUser;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebUser extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithProfile;
    use WithCRMEditAuthorisation;

    private WebUser $webUser;

    public function handle(WebUser $webUser, array $modelData): WebUser
    {
        $webUser = $this->processProfileAvatar($modelData, $webUser);
        if (Arr::exists($modelData, 'password')) {
            data_set($modelData, 'password', Hash::make($modelData['password']));
            data_set($modelData, 'auth_type', WebUserAuthTypeEnum::DEFAULT);
            data_set($modelData, 'data.legacy_password', null);
        }

        data_forget($modelData, 'image');
        if ($webUser->is_root) {
            $customerDataToUpdate = [];
            if (Arr::has($modelData, 'contact_name')) {
                $customerDataToUpdate['contact_name'] = Arr::pull($modelData, 'contact_name');
            }
            if (Arr::has($modelData, 'email')) {
                $customerDataToUpdate['email'] = Arr::pull($modelData, 'email');
            }
            if (Arr::has($modelData, 'phone')) {
                $customerDataToUpdate['phone'] = Arr::pull($modelData, 'phone');
            }
            UpdateCustomer::make()->action($webUser->customer, $customerDataToUpdate);
        }

        $webUser = $this->update($webUser, $modelData, ['data', 'settings']);

        if (Arr::hasAny($webUser->getChanges(), ['status'])) {
            CustomerHydrateWebUsers::dispatch($webUser->customer)->delay($this->hydratorsDelay);
        }

        return $webUser;
    }

    public function rules(): array
    {
        $rules = [
            'username'   => [
                'sometimes',
                'required',
                $this->strict ? new AlphaDashDot() : 'string',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                ),
            ],
            'email'      => [
                'sometimes',
                'nullable',
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                ),

            ],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'contact_name' => ['sometimes'],
            'data'       => ['sometimes', 'array'],
            'password'   => ['sometimes', 'required', app()->isLocal() || app()->environment('testing') || !$this->strict ? Password::min(3) : Password::min(8)],
            'is_root'    => ['sometimes', 'boolean']
        ];

        if (!$this->strict) {

            $rules                    = $this->noStrictUpdateRules($rules);

        }

        return $rules;
    }

    public function asController(WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->webUser = $webUser;
        $this->initialisationFromShop($webUser->shop, $request);

        return $this->handle($webUser, $this->validatedData);
    }


    public function action(WebUser $webUser, $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): WebUser
    {
        $this->strict = $strict;
        if (!$audit) {
            WebUser::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->webUser        = $webUser;
        $this->initialisationFromShop($webUser->shop, $modelData);

        return $this->handle($webUser, $this->validatedData);
    }


}
