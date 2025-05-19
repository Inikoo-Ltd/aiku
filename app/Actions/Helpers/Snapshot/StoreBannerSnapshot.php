<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:05 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Slide\StoreSlide;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Banner;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreBannerSnapshot extends OrgAction
{
    use WithWebEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Banner $banner, array $modelData, ?array $slides): Snapshot
    {
        data_set($modelData, 'group_id', $banner->group_id);
        data_set($modelData, 'scope', SnapshotScopeEnum::BANNER);
        data_set($modelData, 'builder', SnapshotBuilderEnum::AIKU_BANNERS_V1);

        data_set(
            $modelData,
            'checksum',
            md5(
                json_encode(
                    Arr::get($modelData, 'layout')
                )
            )
        );


        return DB::transaction(function () use ($banner, $modelData, $slides) {
            /** @var Snapshot $snapshot */
            $snapshot = $banner->snapshots()->create($modelData);
            $snapshot->stats()->create();

            if ($slides) {
                foreach ($slides as $slide) {
                    StoreSlide::run(
                        snapshot: $snapshot,
                        modelData: $slide,
                    );
                }
            }

            return $snapshot;
        });
    }

    public function rules(): array
    {
        return [
            'layout'         => ['required', 'array'],
            'state'          => ['sometimes', Rule::enum(SnapshotStateEnum::class)],
            'comment'        => ['sometimes','nullable', 'string'],
            'publisher_id'   => ['sometimes','nullable', 'integer'],
            'publisher_type' => ['sometimes','nullable', 'string'],
            'published_at'   => ['sometimes','nullable', 'date'],
            'first_commit'   => ['sometimes','required', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Banner $banner, array $modelData, ?array $slides, int $hydratorsDelay = 0): Snapshot
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($banner->shop, $modelData);

        return $this->handle($banner, $this->validatedData, $slides);
    }

}
