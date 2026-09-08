<?php

namespace App\Http\Resources\Web;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Snapshot;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property mixed $id
 * @property mixed $title
 * @property mixed $canonical_url
 * @property mixed $published_layout
 * @property Snapshot|null $liveSnapshot
 * @property Carbon|null $last_published_at
 */
class BlogsIrisResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $fieldValue = Arr::get($this->published_layout, 'web_blocks.0.web_block.layout.data.fieldValue');
        $imageData  = Arr::get($fieldValue, 'image');

        $publishedAt = Arr::get($fieldValue, 'published_date')
            ?? $this->liveSnapshot?->published_at
            ?? $this->last_published_at;

        return [
            'id'                        => $this->id,
            'title'                     => $this->title,
            'image_src'                 => Arr::get($imageData, 'source'),
            'third_party_image_preview' => Arr::get($fieldValue, 'third_party_image_preview'),
            'image_alt'                 => Arr::get($imageData, 'alt'),
            'url'                       => ShowIrisWebpage::make()->getEnvironmentUrl($this->canonical_url),
            'published_at'              => $publishedAt ? Carbon::parse($publishedAt)->format('D, jS F Y') : null,
        ];
    }
}
