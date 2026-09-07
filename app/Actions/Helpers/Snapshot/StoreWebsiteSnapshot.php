<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 10:05:33 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StoreWebsiteSnapshot extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Website $website, array $modelData): Snapshot
    {
        data_set($modelData, 'builder', SnapshotBuilderEnum::AIKU_WEB_BLOCKS_V1);
        data_set(
            $modelData,
            'checksum',
            md5(
                json_encode(
                    Arr::get($modelData, 'layout')
                )
            )
        );
        data_set($modelData, 'group_id', $website->group_id);


        return DB::transaction(function () use ($website, $modelData) {

            /** @var Snapshot $snapshot */
            $snapshot = $website->snapshots()->create($modelData);
            $snapshot->stats()->create();
            return $snapshot;
        });
    }

    public function rules(): array
    {
        $rules = [
            'layout' => ['required', 'array'],
            'scope'  => ['required', Rule::enum(SnapshotScopeEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Website $website, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Snapshot
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromShop($website->shop, $modelData);

        return $this->handle($website, $this->validatedData);
    }
}
