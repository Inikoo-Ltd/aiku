<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Models\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveShopDataAllegroChannel
{
    use AsAction;

    public function handle(AllegroUser $allegroUser): AllegroUser
    {
        try {
            // Get user info from Allegro API
            $userInfo = $allegroUser->getUserInfo();

            if ($userInfo) {
                $data = $allegroUser->data ?? [];

                // Save user/seller information
                data_set($data, 'user_id', Arr::get($userInfo, 'id'));
                data_set($data, 'login', Arr::get($userInfo, 'login'));
                data_set($data, 'email', Arr::get($userInfo, 'email'));
                data_set($data, 'company_name', Arr::get($userInfo, 'company_name'));

                $allegroUser->update([
                    'data' => $data,
                    'email' => Arr::get($userInfo, 'email') ?? $allegroUser->email,
                    'username' => Arr::get($userInfo, 'login') ?? $allegroUser->username,
                ]);
            }

            return $allegroUser->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to save Allegro shop data: ' . $e->getMessage());
            return $allegroUser;
        }
    }
}
