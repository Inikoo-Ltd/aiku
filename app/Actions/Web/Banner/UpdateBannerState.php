<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Portfolio\Banner\BannerStateEnum;
use App\Http\Resources\Portfolio\BannerResource;
use App\Models\Portfolio\Banner;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateBannerState
{
    use WithActionUpdate;

    public bool $isAction = false;

    public function handle(Banner $banner, array $modelData): Banner
    {
        switch ($modelData['state']) {
            case BannerStateEnum::LIVE->value:
                $modelData['live_at'] = now();
                break;
            case BannerStateEnum::SWITCH_OFF->value:
                $modelData['switch_off_at'] = now();
                break;
        }

        $this->update($banner, $modelData, ['data', 'layout']);

        return $banner;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isAction) {
            return true;
        }

        return $request->get('customerUser')->hasPermissionTo('portfolio.banners.edit');
    }

    public function rules(): array
    {
        return [
            'state' => ['sometimes', 'required', Rule::in(BannerStateEnum::values())],
        ];
    }

    public function asController(Banner $banner, $state): Banner
    {
        $this->setRawAttributes([
            'state' => $state,
        ]);

        return $this->handle($banner, $this->validateAttributes());
    }

    public function action(Banner $banner, $modelData): Banner
    {
        $this->isAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($banner, $validatedData);
    }

    public function jsonResponse(Banner $website): BannerResource
    {
        return new BannerResource($website);
    }
}
