<?php

namespace App\Actions\Helpers\Snapshot;

use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsiteDialogSnapshot
{
    use AsAction;

    public function handle(WebsiteDialog $websiteDialog, array $modelData): Snapshot
    {
        data_set($modelData, 'group_id', $websiteDialog->group_id);
        data_set($modelData, 'scope', SnapshotScopeEnum::WEBSITE_DIALOG);
        data_set($modelData, 'builder', SnapshotBuilderEnum::AIKU_WEBSITE_DIALOG_V1);
        data_set($modelData, 'checksum', md5(json_encode(Arr::get($modelData, 'layout'))));

        /** @var Snapshot $snapshot */
        $snapshot = $websiteDialog->snapshots()->create($modelData);

        return $snapshot;
    }
}
