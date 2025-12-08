<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class ToggleAnnouncement extends OrgAction
{
    use WithActionUpdate;

    public function handle(Announcement $announcement, ?string $status = null): void
    {
        $this->update($announcement, [
            'status' => $status,
        ]);
        ClearCacheByWildcard::run("irisData:website:$announcement->website_id:*");

    }

    public function asController(Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->initialisation($website->organisation, $request);
        $status = $announcement->status === AnnouncementStatusEnum::ACTIVE ? AnnouncementStatusEnum::INACTIVE : AnnouncementStatusEnum::ACTIVE;

        $this->handle($announcement, $status->value);
    }
}
