<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The "new" mark on the wallpaper button stays until the person has opened the picker once in that season.
 */
class MarkStaffChatWallpapersSeen
{
    use AsAction;

    /**
     * @return string[]
     */
    public function handle(User $user): array
    {
        $stored = (array) Arr::get($user->settings, 'chat_wallpaper', []);
        $seen   = array_values(array_unique([...(array) ($stored['seen'] ?? []), ...StaffChatWallpaper::openSeasonKeys()]));

        $user->update(['settings' => [...$user->settings, 'chat_wallpaper' => [...$stored, 'seen' => $seen]]]);

        return $seen;
    }

    public function asController(ActionRequest $request): array
    {
        return ['seen' => $this->handle($request->user())];
    }
}
