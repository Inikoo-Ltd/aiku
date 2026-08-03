<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateAnnouncements implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $websiteId): string
    {
        return $websiteId ?? 'empty';
    }

    public function handle(int|null $websiteId): void
    {
        if (!$websiteId) {
            return;
        }

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        $stats = [
            'number_announcements'          => $website->announcements()->count(),
            'number_active_announcements'   => $website->announcements()->where('status', 'active')->count(),
            'number_inactive_announcements' => $website->announcements()->where('status', 'inactive')->count(),
        ];

        $website->webStats->update($stats);
    }
}
