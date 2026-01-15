<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 2 Jan 2026 11:54:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Snapshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateWorkshopMailShot extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $email = $mailshot->email;
        $outbox = $mailshot->outbox;

        /** @var Snapshot $snapshot */

        $snapshot = $email->unpublishedSnapshot;
        if ($snapshot) {
            // update existing snapshot
            $modelData['builder'] = $outbox->builder->value ?? $snapshot->builder->value;
            $this->update($snapshot, $modelData);
            unset($modelData['builder']);
        } else {
            $snapshot = StoreEmailSnapshot::run(
                $email,
                [
                    'builder' => $outbox->builder->value ?? SnapshotBuilderEnum::BEEFREE,
                    'state'          => SnapshotStateEnum::UNPUBLISHED,
                    'published_at'   => now(),
                    'layout'         => Arr::get($modelData, 'layout'),
                    'first_commit'   => false,
                    'comment'        => Arr::get($modelData, 'comment'),
                    'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                    'publisher_type' => Arr::get($modelData, 'publisher_type'),
                ]
            );
        }


        $updateData = [
            'unpublished_snapshot_id'   => $snapshot->id
        ];

        $this->update($email, $updateData);

        return $mailshot;
    }

    public function rules(): array
    {
        return [
            'layout' => ['required']
        ];
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisation($mailshot->organisation, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
