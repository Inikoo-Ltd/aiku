<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\GuestResource;
use App\Models\SysAdmin\Guest;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Rules\Phone;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateGuest extends GrpAction
{
    use WithActionUpdate;

    private bool $validatePhone = false;

    private Guest $guest;

    public function handle(Guest $guest, array $modelData): Guest
    {
        $credentials = Arr::only($modelData, ['username', 'password']);

        data_forget($modelData, 'username');
        data_forget($modelData, 'password');

        $guest = $this->update($guest, $modelData, [
            'data',
        ]);

        if ($guest->wasChanged('status')) {
            if (! $guest->status) {
                foreach ($guest->users as $user) {
                    UpdateUser::make()->action($user, ['status' => false]);
                }
            }
        }
        if (! empty($credentials) && $user = $guest->getUser()) {
            UpdateUser::run($user, $credentials);
        }

        return $guest;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function rules(): array
    {
        $phoneValidation = ['sometimes', 'nullable'];

        if ($this->validatePhone) {
            $phoneValidation[] = new Phone;
        }

        $rules = [
            'code' => [
                'sometimes',
                'string',
                'max:12',
                Rule::notIn(['export', 'create']),
                new IUnique(
                    table: 'guests',
                    extraConditions: [
                        [
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->guest->id,
                        ],
                    ]
                ),

            ],
            'status' => ['sometimes'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => $phoneValidation,
            'identity_document_number' => ['sometimes', 'nullable', 'string'],
            'identity_document_type' => ['sometimes', 'nullable', 'string'],
        ];

        if ($user = $this->guest->getUser()) {
            $rules['username'] = [
                'sometimes',
                'required',
                'lowercase',
                new AlphaDashDot,

                Rule::notIn(['export', 'create']),
                new IUnique(
                    table: 'users',
                    extraConditions: [

                        [
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $user->id,
                        ],
                    ]
                ),

            ];
            $rules['password'] = [
                'sometimes',
                'required',
                app()->isLocal() || app()->environment('testing') ? 'min:8' : Password::min(8),
            ];
        }

        return $rules;
    }

    public function asController(Guest $guest, ActionRequest $request): Guest
    {
        $this->guest = $guest;

        $this->initialisation(group(), $request);

        return $this->handle($guest, $this->validatedData);
    }

    public function action(Guest $guest, $modelData): Guest
    {
        $this->asAction = true;
        $this->guest = $guest;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($guest, $validatedData);
    }

    public function jsonResponse(Guest $guest): GuestResource
    {
        return new GuestResource($guest);
    }
}
