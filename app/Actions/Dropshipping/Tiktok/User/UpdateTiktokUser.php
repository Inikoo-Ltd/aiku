<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateTiktokUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, array $modelData): TiktokUser
    {
        $tiktokUser = $this->update($tiktokUser, $modelData);

        CheckTiktokChannel::run($tiktokUser);

        return $tiktokUser;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'access_token' => ['sometimes', 'string'],
            'access_token_expire_in' => ['sometimes'],
            'refresh_token' => ['sometimes', 'string'],
            'refresh_token_expire_in' => ['sometimes'],
            'tiktok_shop_id' => ['sometimes', 'string'],
            'tiktok_shop_chiper' => ['sometimes', 'string'],
            'data' => ['sometimes']
        ];
    }

    public function action(TiktokUser $tiktokUser, array $modelData): TiktokUser
    {
        $this->initialisationActions($tiktokUser->customer, $modelData);

        return $this->handle($tiktokUser, $this->validatedData);
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): TiktokUser
    {
        $this->initialisation($request);

        return $this->handle($tiktokUser, $this->validatedData);
    }
}
