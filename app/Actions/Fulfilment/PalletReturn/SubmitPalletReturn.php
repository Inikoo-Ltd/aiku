<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPalletReturn extends OrgAction
{
    use WithActionUpdate;
    use WithSubmitConformPalletReturn;

    public function handle(PalletReturn $palletReturn, array $modelData, bool $sendNotifications = false): PalletReturn
    {
        DB::transaction(function () use ($palletReturn, $modelData) {
            $this->ensureReturnIsInProcess($palletReturn);
            $this->ensureStockIsAvailable($palletReturn);

            $modelData['submitted_at'] = now();

            $this->processChangeState(PalletReturnStateEnum::SUBMITTED, $palletReturn, $modelData);
        });

        if ($sendNotifications) {
            SendPalletReturnNotification::run($palletReturn);
        }

        return $palletReturn;
    }

    private function ensureReturnIsInProcess(PalletReturn $palletReturn): void
    {
        $state = PalletReturn::query()->whereKey($palletReturn->id)->lockForUpdate()->value('state');

        if ($state !== PalletReturnStateEnum::IN_PROCESS->value) {
            throw ValidationException::withMessages([
                'message' => __('This return is :state and cannot be submitted.', ['state' => PalletReturnStateEnum::labels()[$state] ?? $state]),
            ]);
        }
    }
}
