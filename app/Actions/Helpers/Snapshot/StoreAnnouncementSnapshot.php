<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 10:38:27 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Models\Announcement;
use App\Models\Helpers\Snapshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreAnnouncementSnapshot
{
    use AsAction;

    public function handle(Announcement $announcement, array $modelData): Snapshot
    {
        data_set(
            $modelData,
            'checksum',
            md5(
                json_encode(
                    Arr::get($modelData, 'layout')
                )
            )
        );

        $snapshot = Snapshot::create($modelData);
        $announcement->snapshots()->save($snapshot);
        $announcement->saveQuietly();

        return $snapshot;
    }
}
