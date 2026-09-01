<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateWixUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WixUser $wixUser, array $modelData, bool $isNeedCheck = true): WixUser
    {
        /** @var WixUser $wixUser */
        $wixUser = $this->update($wixUser, $modelData);

        if ($isNeedCheck) {
            CheckWixChannel::run($wixUser);
        }

        return $wixUser;
    }

    public function rules(): array
    {
        return [
            'name'                   => ['sometimes', 'string'],
            'email'                  => ['sometimes', 'nullable', 'email'],
            'wix_site_id'            => ['sometimes', 'nullable', 'string'],
            'site_url'               => ['sometimes', 'nullable', 'string'],
            'access_token'           => ['sometimes', 'nullable', 'string'],
            'access_token_expire_in' => ['sometimes', 'nullable'],
            'data'                   => ['sometimes'],
            'settings'               => ['sometimes'],
        ];
    }

    public function action(WixUser $wixUser, array $modelData): WixUser
    {
        $this->asAction = true;
        $this->initialisationActions($wixUser->customer, $modelData);

        return $this->handle($wixUser, $this->validatedData);
    }

    public function asController(WixUser $wixUser, ActionRequest $request): WixUser
    {
        $this->initialisation($request);

        return $this->handle($wixUser, $this->validatedData);
    }
}
