<?php

namespace App\Actions\Iris\Json;

use App\Actions\IrisAction;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class GetBanner extends IrisAction
{
    public function handle(Website $website, int $bannerId): array
    {
        $banner = Banner::where('website_id', $website->id)
            ->where('id', $bannerId)
            ->first();

        if (!$banner) {
            return [
                'success' => false,
                'message' => 'Banner not found',
            ];
        }

        return [
            'id' => $banner->id,
            'slug' => $banner->slug,
            'type' => $banner->type?->value,
            'state' => $banner->state,
            'compiled_layout' => $banner->compiled_layout,
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $bannerId = (int) $request->route('banner');

        return $this->handle($this->website, $bannerId);
    }

    public function jsonResponse(array $data): array
    {
        return $data;
    }
}
