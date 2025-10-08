<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateAnnouncements
{
    use AsAction;

    public function handle(Website $website): void
    {
        $stats = [
            'number_announcements' => $website->announcements()->count(),
        ];

        $website->stats->update($stats);
    }
}
