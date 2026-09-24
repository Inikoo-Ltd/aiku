<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User\UI;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ShowTiktokUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser): array
    {
        return [
            'data' => [
                'authorized_shop' => Arr::get($tiktokUser->data, 'authorized_shop', []),
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route()->tiktokUser->customer_id === $request->user()?->customer_id;
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($tiktokUser);
    }
}
