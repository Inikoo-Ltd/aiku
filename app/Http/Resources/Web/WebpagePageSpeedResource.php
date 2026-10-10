<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Enums\UI\Web\WebpageTabsEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string|null $title
 * @property mixed $type
 * @property string $organisation_slug
 * @property string $shop_slug
 * @property string $website_slug
 * @property int $samples
 * @property mixed $lcp
 * @property mixed $inp
 * @property mixed $cls
 * @property int|null $status
 */
class WebpagePageSpeedResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'code'     => $this->code,
            'title'    => $this->title,
            'typeIcon' => $this->type->stateIcon()[$this->type->value] ?? ['fal', 'fa-browser'],
            'route'    => [
                'name'       => 'grp.org.shops.show.web.webpages.show',
                'parameters' => [
                    'organisation' => $this->organisation_slug,
                    'shop'         => $this->shop_slug,
                    'website'      => $this->website_slug,
                    'webpage'      => $this->slug,
                    'tab'          => WebpageTabsEnum::ANALYTICS->value,
                ],
            ],
            'samples'  => (int) $this->samples,
            'lcp'      => $this->lcp !== null ? (int) $this->lcp : null,
            'inp'      => $this->inp !== null ? (int) $this->inp : null,
            'cls'      => $this->cls !== null ? (float) $this->cls : null,
            'status'   => $this->status !== null ? (int) $this->status : null,
        ];
    }
}
