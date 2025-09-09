<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitImages extends GrpAction
{
    use WithActionUpdate;

    public function handle(TradeUnit $tradeUnit, array $modelData)
    {
        $imageTypeMapping = [
            'image_id' => 'main',
            'front_image_id' => 'front',
            '34_image_id' => '34',
            'left_image_id' => 'left',
            'right_image_id' => 'right',
            'back_image_id' => 'back',
            'top_image_id' => 'top',
            'bottom_image_id' => 'bottom',
            'size_comparison_image_id' => 'size_comparison',
        ];

        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];
            
            if ($mediaId === null) {
                $tradeUnit->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot($tradeUnit->images()
                        ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                        ->first()?->id, 
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);
                
                if ($media) {
                    $tradeUnit->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($tradeUnit, $modelData);

        return $tradeUnit;
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'front_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            '34_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'left_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'right_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'back_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'top_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'bottom_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'size_comparison_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'video_url' => ['sometimes', 'nullable'],
        ];
    }


    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }
}
