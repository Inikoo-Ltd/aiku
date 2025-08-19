<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:04 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSnapshot extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Snapshot $snapshot, array $modelData): Snapshot
    {
        $this->update($snapshot, $modelData, ['layout']);
        return $snapshot;
    }

    public function rules(): array
    {
        $rules = [
            'state'           => ['sometimes', Rule::enum(SnapshotStateEnum::class)],
            'published_until' => ['sometimes', 'date'],
            'label'           => ['sometimes', 'nullable', 'string']
        ];

        if (!$this->strict) {
            $rules['layout']          = ['sometimes', 'array'];
            $rules['compiled_layout'] = ['sometimes', 'nullable', 'string'];
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
