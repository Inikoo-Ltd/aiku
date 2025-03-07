<?php

/*
 * author Arya Permana - Kirin
 * created on 16-12-2024-09h-40m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateEmailUnpublishedSnapshot extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Snapshot $snapshot, array $modelData): Snapshot
    {
        $this->update($snapshot, $modelData);
        return $snapshot;
    }

    public function rules(): array
    {
        $rules = [
            'state'           => ['sometimes', Rule::enum(SnapshotStateEnum::class)],
            'published_until' => ['sometimes', 'date'],
            'layout'          => ['sometimes'],
            'compiled_layout' => ['sometimes', 'nullable']
        ];

        if (!$this->strict) {
            $rules['published_at']    = ['sometimes', 'nullable', 'date'];
            $rules['fetched_at']      = ['sometimes', 'nullable', 'date'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Snapshot $snapshot, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Snapshot
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($snapshot->group, $modelData);

        return $this->handle($snapshot, $this->validatedData);
    }

    public function asController(Snapshot $snapshot, ActionRequest $request)
    {
        $this->initialisationFromGroup($snapshot->group, $request);
        return $this->handle($snapshot, $this->validatedData);
    }

}
