<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:36:36 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ConnectMayaWithQRCode
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(User $user, array $modelData): array
    {
        return [
            'token' => $user->createToken(Arr::get($modelData, 'device_name', 'unknown-device'))->plainTextToken,
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'ulid'],
            'organisation_user_id' => ['required', 'exists:users,id'],
            'device_name' => ['required', 'string'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $userId = Cache::get('profile-app-qr-code:'.$this->get('code'));
            if ($userId) {
                $this->fill([
                    'organisation_user_id' => $userId,
                ]);
                Cache::forget('profile-app-qr-code:'.$this->get('code'));
            }
        }
    }

    public function getValidationMessages(): array
    {
        return [
            'organisation_user_id.required' => __('Invalid QR Code'),
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->fillFromRequest($request);

        $validatedData = $this->validateAttributes();

        $user = User::find($validatedData['organisation_user_id']);

        return $this->handle($user, Arr::only($validatedData, ['device_name']));
    }
}
