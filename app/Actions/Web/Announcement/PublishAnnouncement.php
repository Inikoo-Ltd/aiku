<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreAnnouncementSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;

class PublishAnnouncement extends OrgAction
{
    use WithActionUpdate;

    private Customer|Website $parent;
    private string $scope;
    private Customer $customer;

    public function handle(Announcement $announcement, array $modelData): void
    {
        $firstCommit = false;
        if ($announcement->state == AnnouncementStateEnum::IN_PROCESS or $announcement->state == AnnouncementStateEnum::READY) {
            $firstCommit = true;
        }

        $layout = $announcement->unpublishedSnapshot->layout;

        /** @var Snapshot $snapshot */
        $snapshot = StoreAnnouncementSnapshot::run(
            $announcement,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $layout,
                'first_commit'   => $firstCommit,
                'comment'        => Arr::get($modelData, 'comment'),
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        StoreDeployment::run(
            $announcement,
            [
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        $compiled_layout = [];
        if (Arr::exists($modelData, 'compiled_layout')) {
            $compiled_layout = [
                'compiled_layout'          => Arr::get($modelData, 'compiled_layout'),
            ];
        }

        $updateData = [
            'live_snapshot_id'          => $snapshot->id,
            'fields'                    => Arr::get($snapshot->layout, 'fields'),
            'published_fields'          => Arr::get($snapshot->layout, 'fields'),
            'published_message'         => Arr::get($modelData, 'published_message'),
            'container_properties'      => Arr::get($modelData, 'container_properties'),
            'text'                      => Arr::get($snapshot->layout, 'text'),
            'published_checksum'        => md5(json_encode($snapshot->layout)),
            'state'                     => AnnouncementStateEnum::READY,
            'status'                    => AnnouncementStatusEnum::ACTIVE,
            'published_settings'        => Arr::get($snapshot->layout, 'settings'),
            'is_dirty'                  => false,
            ...$compiled_layout
        ];

        if ($announcement->state == AnnouncementStateEnum::IN_PROCESS or $announcement->state == AnnouncementStateEnum::READY) {
            $updateData['ready_at'] = now();
            $updateData['live_at']  = now();
        }

        $scheduleAt                = Arr::get($modelData, 'schedule_at');
        $updateData['schedule_at'] = Carbon::parse($scheduleAt);

        if ($scheduleAt) {
            $updateData['live_at'] = Carbon::parse($scheduleAt);
        } else {
            $updateData['schedule_at'] = null;
        }

        $scheduleFinishAt                 = Arr::get($modelData, 'schedule_finish_at');
        $updateData['schedule_finish_at'] = Carbon::parse($scheduleFinishAt);

        if ($scheduleFinishAt) {
            $updateData['closed_at']          = Carbon::parse($scheduleFinishAt);
        } else {
            $updateData['schedule_finish_at'] = null;
        }

        ToggleAnnouncement::dispatch($announcement, AnnouncementStatusEnum::ACTIVE->value)->delay($updateData['live_at']);

        if (! blank($updateData['schedule_finish_at'])) {
            ToggleAnnouncement::dispatch($announcement, AnnouncementStatusEnum::INACTIVE->value)->delay($updateData['schedule_finish_at']);
        }

        $this->update($announcement, $updateData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                 => ['sometimes', 'string'],
            'schedule_at'          => ['sometimes', 'date', 'nullable'],
            'schedule_finish_at'   => ['sometimes', 'date', 'nullable'],
            'published_message'    => ['sometimes', 'string'],
            'fields'               => ['sometimes', 'array'],
            'container_properties' => ['sometimes', 'array'],
            'compiled_layout'      => ['sometimes', 'string', 'nullable'],
            'text'                 => ['sometimes', 'string']
        ];
    }

    public function asController(Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->scope    = 'website';
        $this->parent   = $website;
        $this->initialisation($website->organisation, $request);

        $this->handle($announcement, $this->validatedData);
    }
}
