<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\OrgAction;
use App\Actions\Web\Banner\UI\ParseBannerLayout;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Actions\Web\Slide\StoreSlide;
use App\Actions\Web\Slide\UpdateSlide;
use App\Http\Resources\Web\BannerResource;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Banner;
use App\Models\Web\Slide;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateUnpublishedBannerSnapshot extends OrgAction
{
    use WithActionUpdate;

    public function handle(Snapshot $snapshot, array $modelData): Banner
    {
        $layout = Arr::pull($modelData, 'layout');



        list($layout, $slides) = ParseBannerLayout::run($layout);


        data_set($modelData, 'layout', $layout);


        if ($slides) {
            foreach ($slides as $ulid => $slideData) {
                $slide = Slide::where('ulid', $ulid)->first();
                if ($slide) {
                    UpdateSlide::run(
                        $slide,
                        Arr::only($slideData, ['layout', 'imageData'])
                    );
                } else {
                    data_set($slideData, 'ulid', $ulid);
                    StoreSlide::run(
                        snapshot: $snapshot,
                        modelData: $slideData,
                    );
                }
            }
        }

        $snapshot = $this->update($snapshot, $modelData, ['layout']);

        /** @var Banner $banner */
        $banner = $snapshot->parent;

        $banner->update(
            [
                'compiled_layout' => $snapshot->compiledLayout()
            ]
        );

        UpdateBannerImage::run($banner);
        BannerRecordSearch::dispatch($banner);

        return $banner;
    }


    public function rules(): array
    {
        return [
            'layout' => ['sometimes', 'required', 'array:type,delay,common,components,navigation']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set(
            'layout',
            $this->only(['type', 'delay', 'common', 'components', 'navigation'])
        );

    }

    public function asController(Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($banner->shop, $request);

        return $this->handle($banner->unpublishedSnapshot, $this->validatedData);
    }

    public function action(Banner $banner, $modelData): Banner
    {
        $this->asAction = true;
        $this->initialisationFromShop($banner->shop, $modelData);

        return $this->handle($banner->unpublishedSnapshot, $this->validatedData);
    }

    public function jsonResponse(Banner $banner): BannerResource
    {
        return new BannerResource($banner);
    }
}
