<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:05 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Comms\Email;
use App\Models\Helpers\Snapshot;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreEmailSnapshot extends OrgAction
{
    use WithNoStrictRules;


    public function handle(Email $email, array $modelData): Snapshot
    {
        data_set($modelData, 'scope', SnapshotScopeEnum::EMAIL);
        data_set(
            $modelData,
            'checksum',
            md5(
                json_encode(
                    Arr::get($modelData, 'layout')
                )
            )
        );
        data_set($modelData, 'group_id', $email->group_id);

        /** @var Snapshot $snapshot */
        $snapshot = $email->snapshots()->create($modelData);
        $snapshot->stats()->create();
        $snapshot->refresh();

        return $snapshot;
    }


    public function rules(): array
    {
        $rules = [];


        $rules['builder']         = ['required', Rule::enum(SnapshotBuilderEnum::class)];
        $rules['layout']          = ['required', 'array'];
        $rules['compiled_layout'] = ['nullable', 'string'];
        $rules['state']           = ['sometimes', 'required', Rule::enum(SnapshotStateEnum::class)];
        $rules['published_at']    = ['sometimes', 'required', 'date'];
        $rules['recyclable']      = ['sometimes', 'required', 'boolean'];
        $rules['first_commit']    = ['sometimes', 'required', 'boolean'];

        return $this->noStrictStoreRules($rules);
    }

    public function action(Email $email, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Snapshot
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($email->organisation, $modelData);

        return $this->handle($email, $this->validatedData);
    }


}
