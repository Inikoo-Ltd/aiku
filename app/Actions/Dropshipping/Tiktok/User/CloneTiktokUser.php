<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CloneTiktokUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, array $modelData): TiktokUser
    {
        if ($tiktokUser->customerSalesChannel->state === CustomerSalesChannelStateEnum::READY) {
            if ($tiktokUser->tiktok_shop_id !== Arr::get($modelData, 'tiktok_shop_id') && $tiktokUser->tiktok_shop_chiper !== Arr::get($modelData, 'tiktok_shop_chiper')) {
                $tiktokUser = StoreTiktokUser::run($tiktokUser->customer, [
                    'tiktok_id' => $tiktokUser->tiktok_id,
                    'name' => $tiktokUser->name,
                    'username' => $tiktokUser->username,
                    'access_token' => $tiktokUser->access_token,
                    'access_token_expire_in' => $tiktokUser->access_token_expire_in,
                    'refresh_token' => $tiktokUser->refresh_token,
                    'refresh_token_expire_in' => $tiktokUser->refresh_token_expire_in,
                    'tiktok_warehouse_id' => $tiktokUser->tiktok_warehouse_id,
                    'tiktok_shop_id' => Arr::get($modelData, 'tiktok_shop_id'),
                    'tiktok_shop_chiper' => Arr::get($modelData, 'tiktok_shop_chiper')
                ]);
            }
        }

        return $tiktokUser;
    }
}
