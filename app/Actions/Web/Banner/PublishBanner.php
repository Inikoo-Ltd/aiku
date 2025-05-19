<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreBannerSnapshot;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Actions\Web\Banner\UI\ParseBannerLayout;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Http\Resources\Web\BannerResource;
use App\Models\Web\Banner;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class PublishBanner extends OrgAction
{
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    public bool $isAction = false;

    /**
     * @throws \Throwable
     */
    public function handle(Banner $banner, array $modelData): Banner
    {
        $firstCommit = false;
        if ($banner->state == BannerStateEnum::UNPUBLISHED) {
            $firstCommit = true;
        }
        foreach ($banner->snapshots()->where('state', SnapshotStateEnum::LIVE)->get() as $liveSnapshot) {
            UpdateSnapshot::run($liveSnapshot, [
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now()
            ]);
        }

        $layout = Arr::pull($modelData, 'layout');
        list($layout, $slides) = ParseBannerLayout::run($layout);

        $snapshot = StoreBannerSnapshot::make()->action(
            $banner,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $layout,
                'first_commit'   => $firstCommit,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
                'comment'        => Arr::get($modelData, 'comment'),



            ],
            $slides
        );

        StoreDeployment::run(
            $banner,
            [
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        $compiledLayout = $snapshot->compiledLayout();


        $updateData = [
            'live_snapshot_id' => $snapshot->id,
            'compiled_layout'  => $compiledLayout,
            'state'            => BannerStateEnum::LIVE,
        ];

        if ($banner->state == BannerStateEnum::UNPUBLISHED) {
            $updateData['live_at'] = now();
            $updateData['date']    = now();
        }

        $banner->update($updateData);
        BannerRecordSearch::dispatch($banner);
        UpdateBannerImage::run($banner);


        Cache::put('banner_compiled_layout_'.$banner->slug, $banner->compiled_layout, 86400);


        return $banner;
    }


    public function rules(): array
    {
        return [
            'layout'         => ['required', 'array:type,delay,common,components,navigation,published_hash'],
            'comment'        => ['sometimes', 'required', 'string', 'max:1024'],
            'publisher_id'   => ['sometimes'],
            'publisher_type' => ['sometimes', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('layout', $request->only(['type', 'delay', 'common', 'components', 'navigation', 'published_hash']));
        $this->set('publisher_id', $request->user()->id);
        $this->set('publisher_type', 'User');
    }

    /**
     * @throws \Throwable
     */
    public function asController(Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($banner->shop, $request);

        return $this->handle($banner, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Banner $banner, $modelData): Banner
    {
        $this->isAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($banner, $validatedData);
    }

    public function jsonResponse(Banner $banner): BannerResource
    {
        return new BannerResource($banner);
    }

}
