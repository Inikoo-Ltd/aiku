<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Helpers\Snapshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PublishOutbox extends OrgAction
{
    use WithActionUpdate;

    public function handle(Outbox $outbox, array $modelData): Outbox
    {
        $email = $outbox->emailOngoingRun->email;
        $unpublishedSnapshot = $email->unpublishedSnapshot;

        /** @var Snapshot $snapshot */
        $snapshot = StoreEmailSnapshot::run(
            $email,
            [
                'builder' => $unpublishedSnapshot->builder,
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => Arr::get($modelData, 'layout'),
                'compiled_layout' => Arr::get($modelData, 'compiled_layout'),
                'first_commit'   => false,
                'comment'        => Arr::get($modelData, 'comment'),
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
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
            'live_snapshot_id'          => $snapshot->id
        ];

        $this->update($email, $updateData);

        return $this->update($outbox, [
            'state' => OutboxStateEnum::ACTIVE
        ]);
    }

    public function rules(): array
    {
        return [
            'comment' => ['sometimes', 'nullable', 'string'],
            'layout' => ['required'],
            'compiled_layout' => ['required', 'string']
        ];
    }

    public function asController(Shop $shop, Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->initialisation($outbox->organisation, $request);

        return $this->handle($outbox, $this->validatedData);
    }
}
