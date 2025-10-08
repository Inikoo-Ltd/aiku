<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Announcement;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ToggleAnnouncement extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Announcement $announcement, string $status = null): void
    {
        $this->update($announcement, [
            'status' => $status
        ]);
    }

    public function asController(Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->initialisation($website->organisation, $request);
        $status = $announcement->status === AnnouncementStatusEnum::ACTIVE ? AnnouncementStatusEnum::INACTIVE : AnnouncementStatusEnum::ACTIVE;

        $this->handle($announcement, $status->value);
    }
}
