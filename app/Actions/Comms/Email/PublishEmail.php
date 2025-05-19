<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 22:48:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum as EmailTemplateEmailTemplateStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Comms\Email;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Resolvers\UserResolver;

class PublishEmail extends OrgAction
{
    use WithActionUpdate;
    use WithWebAuthorisation;

    public function handle(Email $email, array $modelData): Email
    {
        /** @var User $user */
        $user = UserResolver::resolve();

        if ($user) {
            data_set($modelData, 'publisher_type', class_basename($user), overwrite: false);
            data_set($modelData, 'publisher_id', $user->id, overwrite: false);
        }

        $firstCommit = false;
        //        if ($email->state == EmailTemplateEmailTemplateStateEnum::IN_PROCESS) {
        //            $firstCommit = true;
        //        }

        foreach ($email->snapshots()->where('state', SnapshotStateEnum::LIVE)->get() as $liveSnapshot) {
            UpdateSnapshot::run($liveSnapshot, [
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now()
            ]);
        }

        $currentUnpublishedLayout = $email->unpublishedSnapshot->layout;


        /** @var Snapshot $snapshot */
        $snapshot = StoreEmailSnapshot::run(
            $email,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $currentUnpublishedLayout,
                'first_commit'   => $firstCommit,
                'comment'        => Arr::get($modelData, 'comment'),
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        $deployment = StoreDeployment::run(
            $email,
            [
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        //        $email->stats()->update([
        //            'last_deployed_at' => $deployment->date
        //        ]);

        $updateData = [
            'live_snapshot_id'   => $snapshot->id,
            'published_layout'   => $snapshot->layout,
            'published_checksum' => md5(json_encode($snapshot->layout)),
            //   'state'              => EmailTemplateEmailTemplateStateEnum::LIVE,
            'is_dirty'           => false,
        ];

        //        if ($email->state == EmailTemplateEmailTemplateStateEnum::IN_PROCESS) {
        //            $updateData['live_at'] = now();
        //        }

        $email->update($updateData);

        return $email;
    }

    public function asController(Email $email, ActionRequest $request): Email
    {
        $this->initialisation($email->organisation, $request);

        return $this->handle($email, $this->validatedData);
    }

    public function rules(): array
    {
        $rules = [
            'comment' => ['sometimes','required', 'string', 'max:1024'],
        ];

        if (!$this->strict) {
            $rules['comment']        = ['sometimes', 'nullable', 'string', 'max:1024'];
            $rules['publisher_type'] = ['sometimes', Rule::in(['User'])];
            $rules['publisher_id']   = ['sometimes', 'required', 'integer'];
        }

        return $rules;
    }


    public function jsonResponse(Email $email): string
    {
        return "🚀";
    }

    public function action(Email $email, array $modelData, bool $strict = true): Email
    {
        $this->strict   = $strict;
        $this->asAction = true;


        $this->initialisation($email->organisation, $modelData);

        return $this->handle($email, $this->validatedData);
    }
}
