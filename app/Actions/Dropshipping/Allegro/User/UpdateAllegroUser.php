<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateAllegroUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(AllegroUser $allegroUser, array $modelData): AllegroUser
    {
        /** @var AllegroUser $allegroUser */
        $allegroUser = $this->update($allegroUser, $modelData);

        CheckAllegroChannel::run($allegroUser);

        return $allegroUser;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'username' => ['sometimes', 'string'],
            'access_token' => ['sometimes', 'string'],
            'access_token_expire_in' => ['sometimes'],
            'refresh_token' => ['sometimes', 'string'],
            'refresh_token_expire_in' => ['sometimes'],
            'marketplace_id' => ['sometimes', 'string'],
            'data' => ['sometimes'],
            'settings' => ['sometimes']
        ];
    }

    public function action(AllegroUser $allegroUser, array $modelData): AllegroUser
    {
        $this->initialisationActions($allegroUser->customer, $modelData);

        return $this->handle($allegroUser, $this->validatedData);
    }

    public function asController(AllegroUser $allegroUser, ActionRequest $request): AllegroUser
    {
        $this->initialisation($request);

        return $this->handle($allegroUser, $this->validatedData);
    }
}
