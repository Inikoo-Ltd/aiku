<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Web\WithUploadWebImage;
use App\Models\Announcement;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadImagesToAnnouncement
{
    use AsAction;
    use WithUploadWebImage;

    public function asController(Announcement $announcement, ActionRequest $request): Collection
    {
        return $this->handle($announcement, 'announcement-background', $request->all());
    }
}
