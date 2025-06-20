<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 10:38:27 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateSnapshots;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreWebpageSnapshot extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, array $modelData): Snapshot
    {

        data_set($modelData, 'builder', SnapshotBuilderEnum::AIKU_WEB_BLOCKS_V1);

        data_set(
            $modelData,
            'scope',
            SnapshotScopeEnum::WEBPAGE
        );

        data_set($modelData, 'group_id', $webpage->group_id);

        $snapshot = DB::transaction(function () use ($webpage, $modelData) {

            /** @var Snapshot $snapshot */
            $snapshot = $webpage->snapshots()->create($modelData);
            $snapshot->stats()->create();
            return $snapshot;
        });


        WebpageHydrateSnapshots::dispatch($webpage);

        return $snapshot;
    }

    public function rules(): array
    {
        $rules = [
            'layout' => ['required', 'array'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Webpage $webpage, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Snapshot
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }
}
