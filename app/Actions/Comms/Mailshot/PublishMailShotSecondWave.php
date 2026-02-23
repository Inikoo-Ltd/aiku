<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 11:19:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Snapshot;
use Illuminate\Support\Arr;

class PublishMailShotSecondWave extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $email               = $mailshot->email;
        $unpublishedSnapshot = $email->unpublishedSnapshot;
        $outbox              = $mailshot->outbox;


        /** @var Snapshot $snapshot */
        $snapshot = StoreEmailSnapshot::run(
            $email,
            [
                'builder'         => $unpublishedSnapshot->builder,
                'state'           => SnapshotStateEnum::LIVE,
                'published_at'    => now(),
                'layout'          => Arr::get($modelData, 'layout'),
                'compiled_layout' => Arr::get($modelData, 'compiled_layout'),
                'first_commit'    => false,
                'comment'         => Arr::get($modelData, 'comment'),
                'publisher_id'    => Arr::get($modelData, 'publisher_id'),
                'publisher_type'  => Arr::get($modelData, 'publisher_type'),
            ]
        );

        // update the unpublished layout
        $updateData = [
            'layout' => Arr::get($modelData, 'layout'),
        ];
        $this->update($unpublishedSnapshot, $updateData);


        StoreDeployment::run(
            $email,
            [
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        $updateData = [
            'live_snapshot_id' => $snapshot->id
        ];

        $this->update($email, $updateData);

        $this->update($outbox, [
            'state' => OutboxStateEnum::ACTIVE
        ]);

        $modelData = [
            'ready_at' => now(),
        ];

        SetMailshotAsReady::run($mailshot, $modelData);

        return $mailshot;
    }

    public function rules(): array
    {
        return [
            'comment'         => ['sometimes', 'nullable', 'string'],
            'layout'          => ['required'],
            'compiled_layout' => ['required', 'string']
        ];
    }

    public function action(Mailshot $mailshot, array $modelData, int $hydratorsDelay = 0): Mailshot
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($mailshot->shop, $modelData);

        return $this->handle($mailshot, $this->validatedData);
    }

}
