<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\Dispatching\Sowing;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Sowing;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AssignSowerToSowing extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Sowing $sowing, array $modelData): Sowing
    {
        data_set($modelData, 'sower_assigned_at', now());
        return $this->update($sowing, $modelData);
    }

    public function rules(): array
    {
        return [
            'sower_user_id' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function asController(Sowing $sowing, ActionRequest $request): Sowing
    {
        $this->initialisationFromShop($sowing->shop, $request);

        return $this->handle($sowing, $this->validatedData);
    }

    public function action(Sowing $sowing, array $modelData): Sowing
    {
        $this->initialisationFromShop($sowing->shop, $modelData);

        return $this->handle($sowing, $this->validatedData);
    }
}
